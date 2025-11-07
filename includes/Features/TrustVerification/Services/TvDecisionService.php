<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Services;

// We need to bring all the 'use' statements from Decisions.php
use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Support\Roles;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Requests\CPT; // <-- FIX: ADDED THIS

/**
 * Service class to apply decisions to Trust & Verification requests.
 * This contains the core business logic.
 */
final class TvDecisionService
{
    /**
     * Apply a decision to a single request.
     *
     * @param int    $request_id
     * @param string $action   approve|reject|reopen|resend
     * @param array<string, mixed>  $opts     ['cc_self' => bool]
     */
    public function apply(int $request_id, string $action, array $opts = []): bool
    {
        $action = sanitize_key($action);
        if (! in_array($action, ['approve','reject','reopen','resend'], true)) return false;

        $post = get_post($request_id);
        // This line will now work correctly
        if (! $post || $post->post_type !== CPT::POST_TYPE) return false;

        $user_id = (int) get_post_meta($request_id, '_vp_user_id', true);
        $form_id = (string) get_post_meta($request_id, '_vp_form_id', true);
        $status  = (string) $post->post_status;

        // Config (needed for approve/reject/resend)
        $cfg = $this->loadConfigForForm($form_id);
        if (! $cfg && $action !== 'reopen') return false;

        $now = gmdate('c');
        $by  = get_current_user_id();

        switch ($action) {
            case 'approve': {
                if ($status !== 'vp_pending') return false;
                $role = sanitize_key($cfg['approved_role'] ?? '');
                Meta::appendLog($request_id, 'approve_begin', $by, []);

                // 1. Attempt to update the post status FIRST.
                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_approved'], true);
                if (is_wp_error($updated)) {
                    Meta::appendLog($request_id, 'approve_failed_update', $by, ['error' => $updated->get_error_message()]);
                    return false;
                }

                // 2. NOW that the post is updated, perform side effects.
                $old_roles = [];
                if ($user_id && ($u = get_userdata($user_id))) {
                    $old_roles = (array) $u->roles;
                    update_post_meta($request_id, '_vp_old_roles', $old_roles);
                }

                if ($user_id && $role !== '') {
                    Roles::setSingleRole($user_id, $role);
                }

                $new_roles = [];
                if ($user_id && ($u2 = get_userdata($user_id))) {
                    $new_roles = (array) $u2->roles;
                }

                // 3. Update meta and send emails.
                update_post_meta($request_id, '_vp_processed_by',   $by);
                update_post_meta($request_id, '_vp_processed_date', $now);

                $this->sendDecisionEmail($user_id, $form_id, 'approve', $cfg, $request_id, $opts);
                
                // 4. Final success log.
                Meta::appendLog($request_id, 'approved', $by, [
                    'from_role' => implode(',', $old_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);

                do_action('yardlii_tv_decision_made', $request_id, $user_id, $form_id, 'approve');
                return true;
            }

            case 'reject': {
                if ($status !== 'vp_pending') return false;
                $role = sanitize_key($cfg['rejected_role'] ?? '');
                Meta::appendLog($request_id, 'reject_begin', $by, []);

                // 1. Attempt to update the post status FIRST.
                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_rejected'], true);
                if (is_wp_error($updated)) {
                    Meta::appendLog($request_id, 'reject_failed_update', $by, ['error' => $updated->get_error_message()]);
                    return false;
                }

                // 2. NOW that the post is updated, perform side effects.
                $old_roles = [];
                if ($user_id && ($u = get_userdata($user_id))) {
                    $old_roles = (array) $u->roles;
                    update_post_meta($request_id, '_vp_old_roles', $old_roles);
                }

                if ($user_id && $role !== '') {
                    Roles::setSingleRole($user_id, $role);
                }

                $new_roles = [];
                if ($user_id && ($u2 = get_userdata($user_id))) {
                    $new_roles = (array) $u2->roles;
                }

                // 3. Update meta and send emails.
                update_post_meta($request_id, '_vp_processed_by',   $by);
                update_post_meta($request_id, '_vp_processed_date', $now);

                $this->sendDecisionEmail($user_id, $form_id, 'reject', $cfg, $request_id, $opts);
                
                // 4. Final success log.
                Meta::appendLog($request_id, 'rejected', $by, [
                    'from_role' => implode(',', $old_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);

                do_action('yardlii_tv_decision_made', $request_id, $user_id, $form_id, 'reject');
                return true;
            }

            case 'reopen': {
                if (! in_array($status, ['vp_approved','vp_rejected'], true)) return false;

                // 1. Attempt to update the post status FIRST.
                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_pending'], true);
                if (is_wp_error($updated)) {
                    Meta::appendLog($request_id, 'reopen_failed_update', $by, ['error' => $updated->get_error_message()]);
                    return false;
                }

                // 2. NOW that the post is updated, perform side effects.
                $cur_roles = [];
                if ($user_id && ($u = get_userdata($user_id))) {
                    $cur_roles = (array) $u->roles;
                }

                $old = (array) get_post_meta($request_id, '_vp_old_roles', true);
                if ($user_id) {
                    Roles::restoreRoles($user_id, $old);
                }

                $new_roles = [];
                if ($user_id && ($u2 = get_userdata($user_id))) {
                    $new_roles = (array) $u2->roles;
                }

                // 3. Update meta and log.
                delete_post_meta($request_id, '_vp_processed_by');
                delete_post_meta($request_id, '_vp_processed_date');

                Meta::appendLog($request_id, 'reopened', $by, [
                    'from_role' => implode(',', $cur_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);

                do_action('yardlii_tv_decision_made', $request_id, $user_id, $form_id, 'reopen');
                return true;
            }

            case 'resend': {
                if (! in_array($status, ['vp_approved','vp_rejected'], true)) return false;
                $type = ($status === 'vp_approved') ? 'approve' : 'reject';
                $this->sendDecisionEmail($user_id, $form_id, $type, $cfg, $request_id, $opts);
                Meta::appendLog($request_id, 'resend', $by, ['type' => $type]);

                do_action('yardlii_tv_decision_made', $request_id, $user_id, $form_id, 'resend');
                return true;
            }
        }

        return false;
    }

    /**
     * Send decision email using per-form templates, with centralized Mailer.
     *
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $opts
     */
    private function sendDecisionEmail(
		int $user_id,
		string $form_id,
		string $type,
		array $cfg,
		int $request_id = 0,
		array $opts = []
	): void {
		$user = get_userdata($user_id);
		if (!$user) {
			return;
		}

		// Subject/body with safe defaults (use {{…}} in defaults)
		$subject = ($type === 'approve')
			? (string) ($cfg['approve_subject'] ?? sprintf(__('You’re verified at %s', 'yardlii-core'), '{{site.name}}'))
			: (string) ($cfg['reject_subject']  ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}'));

		$body = ($type === 'approve')
			? (string) ($cfg['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>')
			: (string) ($cfg['reject_body']   ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>');

		// FIX: Removed the redundant legacy strtr() block.
        // The Mailer class will handle both legacy and modern tokens.

		// Build Mailer context (Mailer also renders {{double.curly}} tokens)
		$ccSelf = array_key_exists('cc_self', $opts)
			? (bool) $opts['cc_self']
			: !empty($_REQUEST['tv_send_copy']); // works for single + bulk

		$ctx = [
			'request_id' => $request_id,
			'user'       => $user,
			'form_id'    => $form_id,
			'reply_to'   => $user->user_email ?: '',
			'cc_self'    => $ccSelf,
		];

		// Guard invalid recipient; log skipped
		if (empty($user->user_email) || !is_email($user->user_email)) {
			if ($request_id) {
				\Yardlii\Core\Features\TrustVerification\Support\Meta::appendLog(
					$request_id,
					'email_skipped',
					get_current_user_id(),
					['reason' => 'empty_or_invalid_user_email', 'type' => $type]
				);
			}
			return;
		}

		$ok = (new \Yardlii\Core\Features\TrustVerification\Emails\Mailer())
			->send($user->user_email, $subject, $body, $ctx);

		if ($request_id) {
			\Yardlii\Core\Features\TrustVerification\Support\Meta::appendLog(
				$request_id,
				$ok ? 'email_sent' : 'email_failed',
				get_current_user_id(),
				['type' => $type, 'to' => $user->user_email]
			);
		}
	}


    /**
     * Find per-form config row for a given form_id.
     *
     * @return array<string, mixed>|null
     */
    private function loadConfigForForm(string $form_id): ?array
    {
        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []);
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) {
                return (array) $row;
            }
        }
        return null;
    }
}