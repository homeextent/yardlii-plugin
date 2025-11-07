<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

// We'll need helpers/support classes
use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Support\Roles;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
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
     * @param array $opts ['cc_self' => bool]
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

        $user_id = (int) get_post_meta($request_id, '_vp_user_id', true); // [cite: 1105]
        $form_id = (string) get_post_meta($request_id, '_vp_form_id', true); // [cite: 1106]
        $status = (string) $post->post_status; // [cite: 1107]
        $by = get_current_user_id(); // [cite: 1111]
        $now = gmdate('c'); // [cite: 1110]

        // Load config [cite: 1109]
        $cfg = $this->loadConfigForForm($form_id); // [cite: 1274]
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

    private function handleApprove(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false; // [cite: 1114, 1894]

        $role = sanitize_key($cfg['approved_role'] ?? ''); // [cite: 1115]
        Meta::appendLog($request_id, 'approve_begin', $by, []); // [cite: 1116]

        $old_roles = $user ? (array) $user->roles : []; // [cite: 1120]
        update_post_meta($request_id, '_vp_old_roles', $old_roles); // [cite: 1121]

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role); // [cite: 1123, 1689]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1127]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_approved'], true); // [cite: 1130, 1895]
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

    private function handleReject(int $request_id, string $status, ?WP_User $user, array $cfg, int $by, string $now, array $opts): bool
    {
        if ($status !== 'vp_pending') return false; // [cite: 1142, 1894]

        $role = sanitize_key($cfg['rejected_role'] ?? ''); // [cite: 1143]
        Meta::appendLog($request_id, 'reject_begin', $by, []); // [cite: 1144]

        $old_roles = $user ? (array) $user->roles : []; // [cite: 1148]
        update_post_meta($request_id, '_vp_old_roles', $old_roles); // [cite: 1149]

        if ($user && $role !== '') {
            Roles::setSingleRole($user->ID, $role); // [cite: 1152, 1689]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1155]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_rejected'], true); // [cite: 1157, 1896]
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
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false; // [cite: 1169, 1895, 1896]

        $cur_roles = $user ? (array) $user->roles : []; // [cite: 1174]
        $old_roles_to_restore = (array) get_post_meta($request_id, '_vp_old_roles', true); // [cite: 1176]

        if ($user) {
            Roles::restoreRoles($user->ID, $old_roles_to_restore); // [cite: 1179, 1695]
        }

        $new_roles = $user ? (array) get_userdata($user->ID)->roles : []; // [cite: 1182]

        $updated = wp_update_post(['ID' => $request_id, 'post_status' => 'vp_pending'], true); // [cite: 1184, 1894]
        if (is_wp_error($updated)) return false; // [cite: 1185]

        delete_post_meta($request_id, '_vp_processed_by'); // [cite: 1186]
        delete_post_meta($request_id, '_vp_processed_date'); // [cite: 1187]

        Meta::appendLog($request_id, 'reopened', $by, [ // [cite: 1188]
            'from_role' => implode(',', $cur_roles), // [cite: 1189]
            'to_role' => implode(',', $new_roles), // [cite: 1190]
        ]);

        return true;
    }

    private function handleResend(int $request_id, string $status, ?WP_User $user, string $form_id, array $cfg, int $by, array $opts): bool
    {
        if (!in_array($status, ['vp_approved', 'vp_rejected'], true)) return false; // [cite: 1195, 1895, 1896]

        $type = ($status === 'vp_approved') ? 'approve' : 'reject'; // [cite: 1196]
        
        // Note: $user is passed to sendDecisionEmail, which handles logging if email is skipped [cite: 1251-1261]
        $this->sendDecisionEmail($user, $form_id, $type, $cfg, $request_id, $opts); // [cite: 1197]
        
        Meta::appendLog($request_id, 'resend', $by, ['type' => $type]); // [cite: 1198]
        return true;
    }

    /**
     * Send decision email using per-form templates.
     * This logic is moved from Decisions.php [cite: 1204]
     */
    private function sendDecisionEmail(?WP_User $user, string $form_id, string $type, array $cfg, int $request_id = 0, array $opts = []): void
    {
        if (!$user) {
            // No user, nothing to do [cite: 1213]
            return;
        }

        // Guard invalid recipient; log skipped [cite: 1251]
        if (empty($user->user_email) || !is_email($user->user_email)) {
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

        // Subject/body with safe defaults [cite: 1216]
        $subject = ($type === 'approve')
            ? (string) ($cfg['approve_subject'] ?? sprintf(__('You\'re verified at %s', 'yardlii-core'), '{{site.name}}')) // [cite: 1217]
            : (string) ($cfg['reject_subject'] ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}')); // [cite: 1218]

        $body = ($type === 'approve')
            ? (string) ($cfg['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>') // [cite: 1220]
            : (string) ($cfg['reject_body'] ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>'); // [cite: 1221]

        // Note: The original Decisions.php did a legacy strtr() replacement [cite: 1222-1235]
        // The Mailer class *already* does this replacement (both legacy and modern) [cite: 1368, 1406]
        // We will rely on the Mailer's renderPlaceholders() and not duplicate logic here.

        // Build Mailer context [cite: 1236]
        $ccSelf = (bool) ($opts['cc_self'] ?? false); // [cite: 1237-1239]

        $ctx = [
            'request_id' => $request_id, // [cite: 1241]
            'user' => $user, // [cite: 1243]
            'form_id' => $form_id, // [cite: 1245]
            'reply_to' => $user->user_email ?? '', // [cite: 1247]
            'cc_self' => $ccSelf, // [cite: 1248]
        ];

        // Send via injected Mailer [cite: 1262]
        $ok = $this->mailer->send($user->user_email, $subject, $body, $ctx); // [cite: 1263]

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
     * Logic from Decisions.php [cite: 1274]
     */
    private function loadConfigForForm(string $form_id): ?array
    {
        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []); // [cite: 1276, 1499]
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) { // [cite: 1277]
                return (array) $row; // [cite: 1278]
            }
        }
        return null; // [cite: 1281]
    }
}