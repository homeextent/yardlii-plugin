<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Support\Roles;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Requests\CPT;
use WP_User;

/**
 * Service class to handle the business logic for verification decisions.
 */
final class TvDecisionService
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Apply a decision to a single request.
     *
     * @param int $request_id
     * @param string $action 'approve', 'reject', 'reopen', or 'resend'
     * @param array<string, mixed> $opts ['cc_self' => bool, 'actor_id' => int]
     * @return bool True on success, false on failure or if no-op.
     */
    public function applyDecision(int $request_id, string $action, array $opts = []): bool
    {
        $action = sanitize_key($action);
        if (!in_array($action, ['approve', 'reject', 'reopen', 'resend'], true)) {
            return false;
        }

        $post = get_post($request_id);
        if (!$post || $post->post_type !== CPT::POST_TYPE) {
            return false;
        }

        $user_id = (int) get_post_meta($request_id, '_vp_user_id', true);
        $form_id = (string) get_post_meta($request_id, '_vp_form_id', true);
        $status  = (string) $post->post_status;
        $now     = gmdate('c');
        
        // Allow overriding 'by' via options, default to current user
        $by = isset($opts['actor_id']) ? (int) $opts['actor_id'] : get_current_user_id();

        // Load config
        $cfg = $this->loadConfigForForm($form_id);
        if (!$cfg && $action !== 'reopen') {
            return false;
        }

        $result = false;
        $user   = $user_id ? get_userdata($user_id) : null;

        switch ($action) {
            case 'approve':
                $result = $this->handleApprove($request_id, $status, $user, $cfg, $by, $now, $opts);
                break;
            case 'reject':
                $result = $this->handleReject($request_id, $status, $user, $cfg, $by, $now, $opts);
                break;
            case 'reopen':
                $result = $this->handleReopen($request_id, $status, $user, $by);
                break;
            case 'resend':
                $result = $this->handleResend($request_id, $status, $user, $form_id, $cfg, $by, $opts);
                break;
        }

        if ($result) {
            /**
             * Fires after a verification decision (approve, reject, reopen, resend)
             * has been successfully applied.
             *
             * @param int $request_id The ID of the verification_request post.
             * @param string $action The action taken ('approve', 'reject', 'reopen', 'resend').
             * @param int $user_id The affected user's ID.
             * @param int $admin_id The admin user ID who performed the action.
             */
            do_action('yardlii_tv_decision_made', $request_id, $action, $user_id, $by);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $opts
     */
    private function handleApprove(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false;

        $role = sanitize_key($cfg['approved_role'] ?? '');
        Meta::appendLog($request_id, 'approve_begin', $by, []);

        $old_roles = $user ? (array) $user->roles : [];
        update_post_meta($request_id, '_vp_old_roles', $old_roles);

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role);
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : [];

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_approved'], true);
        if (is_wp_error($updated)) return false;

        update_post_meta($request_id, '_vp_processed_by', $by);
        update_post_meta($request_id, '_vp_processed_date', $now);

        $this->sendDecisionEmail($user, $cfg['form_id'] ?? '', 'approve', $cfg, $request_id, $opts);

        Meta::appendLog($request_id, 'approved', $by, [
            'from_role' => implode(',', $old_roles),
            'to_role'   => implode(',', $new_roles),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $opts
     */
    private function handleReject(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false;

        $role = sanitize_key($cfg['rejected_role'] ?? '');
        Meta::appendLog($request_id, 'reject_begin', $by, []);

        $old_roles = $user ? (array) $user->roles : [];
        update_post_meta($request_id, '_vp_old_roles', $old_roles);

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role);
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : [];

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_rejected'], true);
        if (is_wp_error($updated)) return false;

        update_post_meta($request_id, '_vp_processed_by', $by);
        update_post_meta($request_id, '_vp_processed_date', $now);

        $this->sendDecisionEmail($user, $cfg['form_id'] ?? '', 'reject', $cfg, $request_id, $opts);

        Meta::appendLog($request_id, 'rejected', $by, [
            'from_role' => implode(',', $old_roles),
            'to_role'   => implode(',', $new_roles),
        ]);

        return true;
    }

    private function handleReopen(int $request_id, string $status, ?WP_User $user, int $by): bool
    {
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false;

        $cur_roles = $user ? (array) $user->roles : [];
        $old_roles_to_restore = (array) get_post_meta($request_id, '_vp_old_roles', true);

        if ($user) {
            Roles::restoreRoles($user->ID, $old_roles_to_restore);
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : [];

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

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $opts
     */
    private function handleResend(int $request_id, string $status, ?WP_User $user, string $form_id, array $cfg, int $by, array $opts): bool
    {
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false;

        $type = ($status === 'vp_approved') ? 'approve' : 'reject';
        $this->sendDecisionEmail($user, $form_id, $type, $cfg, $request_id, $opts);

        Meta::appendLog($request_id, 'resend', $by, ['type' => $type]);
        return true;
    }

    /**
     * Send decision email using per-form templates.
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $opts
     */
    private function sendDecisionEmail(?WP_User $user, string $form_id, string $type, array $cfg, int $request_id = 0, array $opts = []): void
    {
        if (!$user) {
            return;
        }

        if (empty($user->user_email) || !is_email($user->user_email)) {
            if ($request_id) {
                Meta::appendLog(
                    $request_id,
                    'email_skipped',
                    get_current_user_id(),
                    ['reason' => 'empty_or_invalid_user_email', 'type' => $type]
                );
            }
            return;
        }

        $subject = ($type === 'approve')
            ? (string) ($cfg['approve_subject'] ?? sprintf(__('You\'re verified at %s', 'yardlii-core'), '{{site.name}}'))
            : (string) ($cfg['reject_subject'] ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}'));

        $body = ($type === 'approve')
            ? (string) ($cfg['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>')
            : (string) ($cfg['reject_body'] ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>');

        $ccSelf = (bool) ($opts['cc_self'] ?? false);

        $ctx = [
            'request_id' => $request_id,
            'user'       => $user,
            'form_id'    => $form_id,
            'reply_to'   => $user->user_email ?? '',
            'cc_self'    => $ccSelf,
        ];

        $ok = $this->mailer->send($user->user_email, $subject, $body, $ctx);

        if ($request_id) {
            Meta::appendLog(
                $request_id,
                $ok ? 'email_sent' : 'email_failed',
                get_current_user_id(),
                ['type' => $type, 'to' => $user->user_email]
            );
        }
    }

    /**
     * Find per-form config row for a given form_id.
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