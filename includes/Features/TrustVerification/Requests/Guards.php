<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use WP_Query;
use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;
use Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings;

/**
 * Submission Guards:
 * - Exposes a unified static API: maybeCreateRequest($user_id, $form_id, $context = []) -> int post_id|0
 * - (Optional) legacy WPUF hooks can be enabled via filter 'yardlii_tv_enable_legacy_wpuf_hooks'
 *
 * Behavior retained from previous version:
 *  - Require matching per-form config for submitted form_id
 *  - Skip if user already has the configured Approved role
 *  - Skip if a duplicate Pending request for (user_id, form_id) exists
 *  - Otherwise, reopen/retarget the latest request for that user (any status) back to Pending
 *    and update form_id; if none exists, create a new Pending request
 *  - Log history and notify admins
 */
final class Guards
{
    /**
     * Register legacy direct hooks (disabled by default).
     * Providers should call maybeCreateRequest() directly.
     */
    public function register(): void
    {
        if (apply_filters('yardlii_tv_enable_legacy_wpuf_hooks', false)) {
            // WPUF profile + register flows: ($user_id, $form_id)
            add_action('wpuf_update_profile', [self::class, 'legacyWPUFHandler'], 10, 2);
            add_action('wpuf_after_register', [self::class, 'legacyWPUFHandler'], 10, 2);
        }
    }

    /** Legacy adapter for direct WPUF hooks */
    public static function legacyWPUFHandler(int $user_id, $form_id): void
    {
        $form_id = (string) $form_id;
        self::maybeCreateRequest($user_id, $form_id, [
            'provider' => 'wpuf',
            'event'    => 'legacy_hook',
        ]);
    }

    /**
     * Create or reuse a verification request for a given user+form.
     * Returns request post ID, or 0 on no-op/failure.
     *
     * @param int    $user_id
     * @param string $form_id
     * @param array  $context  e.g. ['provider'=>'wpuf','event'=>'post_insert','extra'=>[]]
     */
    public static function maybeCreateRequest(int $user_id, string $form_id, array $context = []): int
    {
        $user_id = absint($user_id);
        $form_id = sanitize_text_field($form_id);
        if ($user_id <= 0 || $form_id === '') {
            return 0;
        }

        // 1) per-form config must exist
        $cfg = self::loadConfigForForm($form_id);
        if (!$cfg) {
            return 0;
        }

        // 2) user exists & not already on the target approved role
        $user = get_userdata($user_id);
        if (!$user) {
            return 0;
        }

        $approved_role = sanitize_key($cfg['approved_role'] ?? '');
        if ($approved_role && in_array($approved_role, (array) $user->roles, true)) {
            return 0; // already at the target role for this step
        }

        // 3a) de-dupe: existing pending for (user_id, form_id)?
        $existing = self::findPendingByUserAndForm($user_id, $form_id);
        if ($existing) {
            Meta::appendLog($existing, 'upgrade_submitted', get_current_user_id(), [
                'from_status' => 'vp_pending',
                'from_form'   => $form_id,
                'to_form'     => $form_id,
                'provider'    => (string)($context['provider'] ?? ''),
                'event'       => (string)($context['event'] ?? ''),
            ]);
            self::notifyAdmins($existing, $user_id, $form_id);
            return $existing;
        }

        // 3b) reuse latest request for this user (any status): retarget + reopen to pending
        $latest = self::findLatestByUser($user_id);
        if ($latest) {
            $request_id  = $latest;
            $prev_form   = (string) get_post_meta($request_id, '_vp_form_id', true);
            $prev_status = (string) get_post_status($request_id);

            update_post_meta($request_id, '_vp_form_id', $form_id);
            update_post_meta($request_id, '_vp_old_roles', (array) $user->roles);
            update_post_meta($request_id, '_vp_old_role',  implode(',', (array) $user->roles));
            delete_post_meta($request_id, '_vp_processed_by');
            delete_post_meta($request_id, '_vp_processed_date');

            $updated = wp_update_post([
                'ID'          => $request_id,
                'post_status' => 'vp_pending',
                'post_title'  => sprintf('Request #%d — %s', $request_id, $user->display_name ?: $user->user_login),
            ], true);
            if (is_wp_error($updated)) {
                return 0;
            }

            Meta::appendLog($request_id, 'upgrade_submitted', get_current_user_id(), [
                'from_status' => $prev_status,
                'from_form'   => $prev_form,
                'to_form'     => $form_id,
                'provider'    => (string)($context['provider'] ?? ''),
                'event'       => (string)($context['event'] ?? ''),
            ]);

            self::notifyAdmins($request_id, $user_id, $form_id);
            return $request_id;
        }

        // 4) create a brand new pending request
        $request_id = wp_insert_post([
            'post_type'   => CPT::POST_TYPE,
            'post_status' => 'vp_pending',
            'post_title'  => sprintf('Verification Request — U:%d F:%s', $user_id, $form_id),
        ], true);
        if (is_wp_error($request_id) || !$request_id) {
            return 0;
        }

        update_post_meta($request_id, '_vp_user_id',  $user_id);
        update_post_meta($request_id, '_vp_form_id',  $form_id);
        update_post_meta($request_id, '_vp_action_logs', []);
        update_post_meta($request_id, '_vp_old_roles', (array) $user->roles);
        update_post_meta($request_id, '_vp_old_role',  implode(',', (array) $user->roles));

        Meta::appendLog($request_id, 'created', get_current_user_id(), [
            'form_id'  => $form_id,
            'provider' => (string)($context['provider'] ?? ''),
            'event'    => (string)($context['event'] ?? ''),
        ]);

        wp_update_post([
            'ID'         => $request_id,
            'post_title' => sprintf('Request #%d — %s', $request_id, $user->display_name ?: $user->user_login),
        ]);



// NEW: Employer Vouch Trigger
if ($request_id && !empty($context['employer_email'])) {
    if (class_exists('\Yardlii\Core\Features\TrustVerification\Services\EmployerVouchService')) {
        $mailer = new \Yardlii\Core\Features\TrustVerification\Emails\Mailer();
        $service = new \Yardlii\Core\Features\TrustVerification\Services\EmployerVouchService($mailer);
        $service->initiateVouch($request_id, $context['employer_email']);
        
        // Optional: Add a log entry explicitly stating we sent the email
        Meta::appendLog($request_id, 'vouch_email_sent', 0, ['to' => $context['employer_email']]);
    }
}



        self::notifyAdmins($request_id, $user_id, $form_id);
        return (int) $request_id;
    }

    /* =========================
     * Helpers (static)
     * =======================*/

    /** Find existing pending for (user_id, form_id). */
    private static function findPendingByUserAndForm(int $user_id, string $form_id): int
    {
        $q = new WP_Query([
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => ['vp_pending'],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_vp_user_id', 'value' => $user_id, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => '_vp_form_id', 'value' => $form_id, 'compare' => '='],
            ],
        ]);
        return $q->have_posts() ? (int) $q->posts[0] : 0;
    }

    /** Find most recent request for a user (any status). */
    private static function findLatestByUser(int $user_id): int
    {
        $posts = get_posts([
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => ['vp_pending','vp_approved','vp_rejected'],
            'meta_key'       => '_vp_user_id',
            'meta_value'     => $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        return !empty($posts) ? (int) $posts[0] : 0;
    }

    /** Find saved config row for a given form_id. */
    private static function loadConfigForForm(string $form_id): ?array
    {
        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []);
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) {
                return (array) $row;
            }
        }
        return null;
    }

    /** Notify admins when a request is created/reused/reopened. */
    private static function notifyAdmins(int $request_id, int $user_id, string $form_id): void
    {
        $raw = (string) get_option(GlobalSettings::OPT_EMAILS, '');
        if ($raw === '') return;
        $to = array_values(array_filter(array_map(static function ($s) {
            $email = sanitize_email(trim((string) $s));
            return ($email && is_email($email)) ? $email : null;
        }, explode(',', $raw))));
        if (empty($to)) return;

        $user = get_userdata($user_id);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        // --- FIX 1: Subject format updated ---
        // Changed from '[%s] ...' to '%s: ...'
        $subject = sprintf('%s: New Verification Request from %s',
            $site,
            $user ? ($user->display_name ?: $user->user_login) : ('User ' . $user_id)
        );

        $link =
            admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification&tvsection=requests');

        // --- FIX 2: Body content updated ---
        // Prepare user details
        $user_name = $user ? ($user->display_name ?: $user->user_login) : 'N/A';
        $user_email = $user ? $user->user_email : 'N/A';

        // Add new <li> lines for user name/email
        $message = sprintf(
            '<p>A new verification request is pending.</p>
            <ul>
            <li><strong>User Name:</strong> %s</li>
            <li><strong>User Email:</strong> %s</li>
            <li><strong>User ID:</strong> %d</li>
            <li><strong>Form ID:</strong> %s</li>
            <li><strong>Request ID:</strong> %d</li>
            </ul>
            <p><a href="%s">Open Requests</a></p>',
            esc_html($user_name),  // New
            esc_html($user_email), // New
            $user_id,
            esc_html($form_id),
            $request_id,
            esc_url($link)
        );
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($to as $addr) {
            wp_mail($addr, $subject, $message, $headers);
        }
    }
}
