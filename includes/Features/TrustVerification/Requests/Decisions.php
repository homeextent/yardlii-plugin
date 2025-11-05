<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Support\Roles;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Caps;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

/**
 * Handles single-request actions from the Requests table:
 * approve | reject | reopen | resend
 */
final class Decisions
{
    /** Wire up admin_action hooks */
    public function register(): void
    {
        add_action('admin_action_yardlii_tv_approve', [$this, 'approve']);
        add_action('admin_action_yardlii_tv_reject',  [$this, 'reject']);
        add_action('admin_action_yardlii_tv_reopen',  [$this, 'reopen']);
        add_action('admin_action_yardlii_tv_resend',  [$this, 'resend']);
    }

    /** Approve a single request and redirect back */
    public function approve(): void { $this->handleDecision('approve'); }

    /** Reject a single request and redirect back */
    public function reject(): void { $this->handleDecision('reject'); }

    /** Reopen a single request and redirect back */
    public function reopen(): void { $this->handleDecision('reopen'); }

    /** Resend decision email (approved/rejected) and redirect back */
    public function resend(): void { $this->handleDecision('resend'); }

    /** Common controller for the 4 admin_action endpoints. */
    private function handleDecision(string $action): void
    {
        if (! current_user_can(Caps::MANAGE)) {
            wp_die(__('Insufficient permissions.', 'yardlii-core'));
        }
        check_admin_referer('yardlii_tv_action_nonce');

        $request_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$request_id) wp_die(__('Invalid request.', 'yardlii-core'));

        $post = get_post($request_id);
        if (! $post || $post->post_type !== CPT::POST_TYPE) {
            wp_die(__('Invalid request.', 'yardlii-core'));
        }

        // NEW: pick up the “Send me a copy” checkbox (top/bottom bulk bar or single)
        $sendCopy = ! empty($_REQUEST['tv_send_copy']);

        // Apply and bounce back with a scoped notice
        $ok  = $this->applyDecision($request_id, $action, ['cc_self' => $sendCopy]);
        $ref = wp_get_referer();
        $ref = $ref ? remove_query_arg(['action','action2','_wpnonce','post','tv_notice','tv_count'], $ref)
                    : admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');

        $ref = add_query_arg([
            'tvsection' => 'requests',
            'tv_notice' => $ok ? $action : 'noop',
        ], $ref);

        wp_safe_redirect($ref);
        exit;
    }

    /**
     * Apply a decision to a single request.
     *
     * @param int    $request_id
     * @param string $action   approve|reject|reopen|resend
     * @param array  $opts     ['cc_self' => bool]  // NEW
     */
    public function applyDecision(int $request_id, string $action, array $opts = []): bool
    {
        $action = sanitize_key($action);
        if (! in_array($action, ['approve','reject','reopen','resend'], true)) return false;

        $post = get_post($request_id);
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

                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_approved'], true);
                if (is_wp_error($updated)) return false;

                update_post_meta($request_id, '_vp_processed_by',   $by);
                update_post_meta($request_id, '_vp_processed_date', $now);

                $this->sendDecisionEmail($user_id, $form_id, 'approve', $cfg, $request_id, $opts);
                Meta::appendLog($request_id, 'approved', $by, [
                    'from_role' => implode(',', $old_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);
                return true;
            }

            case 'reject': {
                if ($status !== 'vp_pending') return false;
                $role = sanitize_key($cfg['rejected_role'] ?? '');
                Meta::appendLog($request_id, 'reject_begin', $by, []);

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

                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_rejected'], true);
                if (is_wp_error($updated)) return false;

                update_post_meta($request_id, '_vp_processed_by',   $by);
                update_post_meta($request_id, '_vp_processed_date', $now);

                $this->sendDecisionEmail($user_id, $form_id, 'reject', $cfg, $request_id, $opts);
                Meta::appendLog($request_id, 'rejected', $by, [
                    'from_role' => implode(',', $old_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);
                return true;
            }

            case 'reopen': {
                if (! in_array($status, ['vp_approved','vp_rejected'], true)) return false;

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

                $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_pending'], true);
                if (is_wp_error($updated)) return false;

                delete_post_meta($request_id, '_vp_processed_by');
                delete_post_meta($request_id, '_vp_processed_date');

                Meta::appendLog($request_id, 'reopened', $by, [
                    'from_role' => implode(',', $cur_roles),
                    'to_role'   => implode(',', $new_roles),
                ]);
                return true;
            }

            case 'resend': {
                if (! in_array($status, ['vp_approved','vp_rejected'], true)) return false;
                $type = ($status === 'vp_approved') ? 'approve' : 'reject';
                $this->sendDecisionEmail($user_id, $form_id, $type, $cfg, $request_id, $opts);
                Meta::appendLog($request_id, 'resend', $by, ['type' => $type]);
                return true;
            }
        }

        return false;
    }

    /**
     * Send decision email using per-form templates, with centralized Mailer.
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

    // Subject/body with safe defaults (use {{…}} in defaults; legacy {…} still supported below)
    $subject = ($type === 'approve')
        ? (string) ($cfg['approve_subject'] ?? sprintf(__('You’re verified at %s', 'yardlii-core'), '{{site.name}}'))
        : (string) ($cfg['reject_subject']  ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}'));

    $body = ($type === 'approve')
        ? (string) ($cfg['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>')
        : (string) ($cfg['reject_body']   ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>');

    // Legacy {token} merge (kept so old templates still work)
    $site_title = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $legacy = [
        '{display_name}' => $user->display_name,
        '{user_email}'   => $user->user_email,
        '{user_login}'   => $user->user_login,
        '{form_id}'      => $form_id,
        '{request_id}'   => (string) $request_id,
        '{site_title}'   => $site_title,
        '{site_url}'     => home_url('/'),
    ];
    $subject = strtr($subject, $legacy);
    $body    = strtr($body,    $legacy);

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


    /** Find per-form config row for a given form_id. */
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
