<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

// We'll need helpers/support classes
use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Support\Roles;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Requests\CPT; // <-- FIX: ADDED THIS USE STATEMENT
use WP_User;

/**
 * Service class to handle the business logic for verification decisions.
 * This moves logic out of the admin controller (Decisions.php)
 * and allows it to be reused and tested.
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
     * @param array<string, bool> $opts ['cc_self' => bool]
     * @return bool True on success, false on failure or if no-op.
     */
    public function applyDecision(int $request_id, string $action, array $opts = []): bool
    {
        $action = sanitize_key($action);
        if (!in_array($action, ['approve', 'reject', 'reopen', 'resend'], true)) {
            return false;
        }

        $post = get_post($request_id);
        if (!$post || $post->post_type !== CPT::POST_TYPE) { // [cite: 1104, 1876]
            return false;
        }

        $user_id = (int) get_post_meta($request_id, '_vp_user_id', true); // 
        $form_id = (string) get_post_meta($request_id, '_vp_form_id', true); //
        $status = (string) $post->post_status; // 
        $by = get_current_user_id(); // 
        $now = gmdate('c'); // 
        $by = isset($opts['actor_id']) ? (int) $opts['actor_id'] : get_current_user_id();

        // Load config
        $cfg = $this->loadConfigForForm($form_id); // [cite: 1109]
        if (!$cfg && $action !== 'reopen') {
            return false; // [cite: 1109]
        }

        $result = false;
        $user = $user_id ? get_userdata($user_id) : null;

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

        // **NEW: Item #5 Event Hook**
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
     * @param array<string, bool> $opts
     */
    private function handleApprove(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false; // [cite: 1114]

        $role = sanitize_key($cfg['approved_role'] ?? ''); // [cite: 1115]
        Meta::appendLog($request_id, 'approve_begin', $by, []); // [cite: 1116]

        $old_roles = $user ? (array) $user->roles : []; // [cite: 1118-1120]
        update_post_meta($request_id, '_vp_old_roles', $old_roles); // [cite: 1121]

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role); // [cite: 1122-1123]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1126-1127]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_approved'], true); // [cite: 1130]
        if (is_wp_error($updated)) return false; // [cite: 1131]

        update_post_meta($request_id, '_vp_processed_by', $by); // [cite: 1132]
        update_post_meta($request_id, '_vp_processed_date', $now); // [cite: 1133]

        $this->sendDecisionEmail($user, $cfg['form_id'] ?? '', 'approve', $cfg, $request_id, $opts); // [cite: 1134]

        Meta::appendLog($request_id, 'approved', $by, [ // [cite: 1135]
            'from_role' => implode(',', $old_roles), // [cite: 1136]
            'to_role' => implode(',', $new_roles), // [cite: 1137]
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, bool> $opts
     */
    private function handleReject(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false; // [cite: 1142]

        $role = sanitize_key($cfg['rejected_role'] ?? ''); // [cite: 1143]
        Meta::appendLog($request_id, 'reject_begin', $by, []); // [cite: 1144]

        $old_roles = $user ? (array) $user->roles : []; // [cite: 1146-1148]
        update_post_meta($request_id, '_vp_old_roles', $old_roles); // [cite: 1149]

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role); // [cite: 1150-1152]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1154-1155]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_rejected'], true); // [cite: 1157]
        if (is_wp_error($updated)) return false; // [cite: 1158]

        update_post_meta($request_id, '_vp_processed_by', $by); // [cite: 1159]
        update_post_meta($request_id, '_vp_processed_date', $now); // [cite: 1160]

        $this->sendDecisionEmail($user, $cfg['form_id'] ?? '', 'reject', $cfg, $request_id, $opts); // [cite: 1161]

        Meta::appendLog($request_id, 'rejected', $by, [ // [cite: 1162]
            'from_role' => implode(',', $old_roles), // [cite: 1163]
            'to_role' => implode(',', $new_roles), // [cite: 1164]
        ]);

        return true;
    }

    private function handleReopen(int $request_id, string $status, ?WP_User $user, int $by): bool
    {
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false; // [cite: 1169]

        $cur_roles = $user ? (array) $user->roles : []; // [cite: 1171-1174]
        $old_roles_to_restore = (array) get_post_meta($request_id, '_vp_old_roles', true); // [cite: 1176]

        if ($user) {
            Roles::restoreRoles($user->ID, $old_roles_to_restore); // [cite: 1177-1179]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1181-1182]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_pending'], true); // [cite: 1184]
        if (is_wp_error($updated)) return false; // [cite: 1185]

        delete_post_meta($request_id, '_vp_processed_by'); // [cite: 1186]
        delete_post_meta($request_id, '_vp_processed_date'); // [cite: 1187]

        Meta::appendLog($request_id, 'reopened', $by, [ // [cite: 1188]
            'from_role' => implode(',', $cur_roles), // [cite: 1189]
            'to_role' => implode(',', $new_roles), // [cite: 1190]
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, bool> $opts
     */
    private function handleResend(int $request_id, string $status, ?WP_User $user, string $form_id, array $cfg, int $by, array $opts): bool
    {
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false; // [cite: 1195]

        $type = ($status === 'vp_approved') ? 'approve' : 'reject'; // [cite: 1196]
        
        $this->sendDecisionEmail($user, $form_id, $type, $cfg, $request_id, $opts); // [cite: 1197]
        
        Meta::appendLog($request_id, 'resend', $by, ['type' => $type]); // [cite: 1198]
        return true;
    }

    /**
     * Send decision email using per-form templates.
     * @param array<string, mixed> $cfg
     * @param array<string, bool> $opts
     */
    private function sendDecisionEmail(?WP_User $user, string $form_id, string $type, array $cfg, int $request_id = 0, array $opts = []): void
    {
        if (!$user) {
            return; // [cite: 1213]
        }

        if (empty($user->user_email) || !is_email($user->user_email)) { // [cite: 1251]
            if ($request_id) {
                Meta::appendLog( // [cite: 1253]
                    $request_id,
                    'email_skipped', // [cite: 1255]
                    get_current_user_id(), // [cite: 1256]
                    ['reason' => 'empty_or_invalid_user_email', 'type' => $type] // [cite: 1257]
                );
            }
            return; // [cite: 1260]
        }

        $subject = ($type === 'approve')
            ? (string) ($cfg['approve_subject'] ?? sprintf(__('You\'re verified at %s', 'yardlii-core'), '{{site.name}}')) // [cite: 1217]
            : (string) ($cfg['reject_subject'] ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}')); // [cite: 1218]

        $body = ($type === 'approve')
            ? (string) ($cfg['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>') // [cite: 1220]
            : (string) ($cfg['reject_body'] ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>'); // [cite: 1221]

        $ccSelf = (bool) ($opts['cc_self'] ?? false); // [cite: 1238]

        $ctx = [
            'request_id' => $request_id, // [cite: 1241]
            'user' => $user, // [cite: 1244]
            'form_id' => $form_id, // [cite: 1246]
            'reply_to' => $user->user_email ?? '', // [cite: 1247]
            'cc_self' => $ccSelf, // [cite: 1249]
        ];

        $ok = $this->mailer->send($user->user_email, $subject, $body, $ctx); // [cite: 1262-1263]

        if ($request_id) {
            Meta::appendLog( // [cite: 1265]
                $request_id,
                $ok ? 'email_sent' : 'email_failed', // [cite: 1267]
                get_current_user_id(), // [cite: 1268]
                ['type' => $type, 'to' => $user->user_email] // [cite: 1269]
            );
        }
    }

    /**
     * Find per-form config row for a given form_id.
     * @return array<string, mixed>|null
     */
    private function loadConfigForForm(string $form_id): ?array
    {
        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []); // [cite: 1276]
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) { // [cite: 1277]
                return (array) $row; // [cite: 1278]
            }
        }
        return null; // [cite: 1281]
    }
}