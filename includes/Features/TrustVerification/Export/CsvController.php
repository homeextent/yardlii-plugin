<?php

declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Export;

use Yardlii\Core\Features\TrustVerification\Requests\CPT;
use Yardlii\Core\Features\TrustVerification\Support\Meta;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs;

final class CsvController
{
    public function register(): void
    {
        add_action('admin_post_yardlii_tv_export_csv',   [$this, 'stream']);
        add_action('admin_post_yardlii_tv_seed_request', [$this, 'seed']);
        add_action('admin_post_yardlii_tv_reset_seed_user', [$this, 'resetSeedUser']);
    }



    public function stream(): void
{
    // Capability gate (custom cap if available, else admins)
    $cap = class_exists('\Yardlii\Core\Features\TrustVerification\Caps')
        ? \Yardlii\Core\Features\TrustVerification\Caps::MANAGE
        : 'manage_options';

    if ( ! current_user_can($cap) ) {
        wp_die(__('Insufficient permissions.', 'yardlii-core'));
    }

    check_admin_referer('yardlii_tv_export_nonce');

    // Stream CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=verification-requests.csv');

    $out = fopen('php://output', 'w');

    // (unchanged) existing columns...
    $columns = [
        'request_id','user_id','user_login','user_email','user_display_name',
        'form_id','submitted_date','status','processed_by','processed_date',
        'old_roles','last_action'
    ];

    // OPTIONAL: if you want the two machine-friendly columns appended:
    // $columns[] = 'submitted_utc';
    // $columns[] = 'processed_by_id';

    fputcsv($out, $columns);

    $q = new \WP_Query([
        'post_type'      => CPT::POST_TYPE,
        'post_status'    => ['vp_pending', 'vp_approved', 'vp_rejected'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($q->posts as $post_id) {
        $u_id   = (int) get_post_meta($post_id, '_vp_user_id', true);
        $u      = get_userdata($u_id);
        $login  = $u ? $u->user_login : '';
        $email  = $u ? $u->user_email : '';
        $name   = $u ? $u->display_name : '';
        $form   = (string) get_post_meta($post_id, '_vp_form_id', true);
        $dateU  = (int) get_post_time('U', true, $post_id); // unix
        $status = get_post_status($post_id);
        $by     = (int) get_post_meta($post_id, '_vp_processed_by', true);
        $by_u   = $by ? get_userdata($by) : null;
        $by_s   = $by_u ? ($by_u->display_name ?: $by_u->user_login) : '';
        $pdate  = (string) get_post_meta($post_id, '_vp_processed_date', true);
        $old    = (array) get_post_meta($post_id, '_vp_old_roles', true);
        $logs   = (array) get_post_meta($post_id, '_vp_action_logs', true);
        $last   = $logs ? (string) end($logs)['action'] : '';

        $row = [
            $post_id, $u_id, $login, $email, $name, $form,
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $dateU),
            $status, $by_s, $pdate, implode('|', $old), $last
        ];

        // OPTIONAL: append machine-friendly fields if you enabled them above:
        // $row[] = gmdate('c', $dateU); // submitted_utc
        // $row[] = $by ?: '';           // processed_by_id

        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}


    // === NEW: seed a Pending request for QA (using a dedicated test user) ========
    public function seed(): void
{
    $cap = class_exists('\Yardlii\Core\Features\TrustVerification\Caps')
        ? \Yardlii\Core\Features\TrustVerification\Caps::MANAGE
        : 'manage_options';

    if ( ! current_user_can($cap) ) {
        wp_die(__('Insufficient permissions.', 'yardlii-core'));
    }

    check_admin_referer('yardlii_tv_seed_nonce');

        // 1) Find or create a dedicated test user (never admin)
        $test_user_id = 0;

        // Reuse if already created before
        $maybe = get_users([
            'meta_key'   => '_yardlii_tv_seed_user',
            'meta_value' => '1',
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        if (!empty($maybe)) {
            $test_user_id = (int) $maybe[0];
        } else {
            // Build a safe, unique login & email that is NOT the current user's email
            $admin_email = get_option('admin_email', '');
            $domain = 'example.org';
            if (is_email($admin_email) && str_contains($admin_email, '@')) {
                $domain = substr(strrchr($admin_email, '@'), 1);
            }

            $login = 'tv_seed_' . strtolower(wp_generate_password(6, false, false));
            $email = 'tv+' . strtolower(wp_generate_password(8, false, false)) . '@' . $domain;
            $pass  = wp_generate_password(20);

            $u_id = wp_insert_user([
                'user_login'   => $login,
                'user_pass'    => $pass,
                'user_email'   => $email,
                'display_name' => 'TV Seed User',
                'role'         => 'subscriber', // never admin
            ]);

            if (!is_wp_error($u_id) && $u_id) {
                $test_user_id = (int) $u_id;
                update_user_meta($test_user_id, '_yardlii_tv_seed_user', '1');
            } else {
                // Fallback: as a last resort, if creation failed, use the CURRENT user
                // but ONLY if they are NOT an admin (so we never demote ourselves).
                $current = get_current_user_id();
                if ($current && !user_can($current, 'manage_options')) {
                    $test_user_id = $current;
                } else {
                    wp_die(__('Could not create test user and no safe fallback available.', 'yardlii-core'));
                }
            }
        }

        // 2) Resolve the form_id to use
        $configs = (array) get_option(FormConfigs::OPT_KEY, []);
        $form_id = '';
        foreach ($configs as $row) {
            if (!empty($row['form_id'])) {
                $form_id = (string) $row['form_id'];
                break;
            }
        }
        if ($form_id === '') {
            $form_id = 'TEST';
        }

        // 3) Insert the Pending request for the test user
        $request_id = wp_insert_post([
            'post_type'   => CPT::POST_TYPE,
            'post_status' => 'vp_pending',
            'post_title'  => sprintf('Verification Request — U:%d F:%s', $test_user_id, $form_id),
        ], true);

        if (!is_wp_error($request_id) && $request_id) {
            update_post_meta($request_id, '_vp_user_id', $test_user_id);
            update_post_meta($request_id, '_vp_form_id', $form_id);
            update_post_meta($request_id, '_vp_action_logs', []);
            Meta::appendLog(
                $request_id,
                'created',
                get_current_user_id(),
                ['form_id' => $form_id, 'source' => 'seed']
            );
        }

        // 4) Redirect back with a friendly notice showing which user was used
        $ref = wp_get_referer();
        if (!$ref) {
        $ref = admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification&tvsection=tools');
        $ref = $ref ?: admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');
        $ref = add_query_arg('tvsection', 'tools', $ref);

        }

        $email_for_notice = '';
        if ($test_user_id) {
            $tu = get_userdata($test_user_id);
            if ($tu) {
                $email_for_notice = $tu->user_email;
            }
        }

        $ref = add_query_arg([
            'tv_seed' => 'ok',
            'uid'     => $test_user_id,
            'email'   => rawurlencode($email_for_notice),
        ], $ref);

        wp_safe_redirect($ref);
        exit;
    }

    public function resetSeedUser(): void
{
    // Capability gate (custom cap if available, else admins)
    $cap = class_exists('\Yardlii\Core\Features\TrustVerification\Caps')
        ? \Yardlii\Core\Features\TrustVerification\Caps::MANAGE
        : 'manage_options';

    if ( ! current_user_can($cap) ) {
        wp_die(__('Insufficient permissions.', 'yardlii-core'));
    }

    check_admin_referer('yardlii_tv_reset_seed_nonce');

        // Find all "seed" users by our meta flag
        $seed_user_ids = get_users([
            'meta_key'   => '_yardlii_tv_seed_user',
            'meta_value' => '1',
            'fields'     => 'ids',
            'number'     => -1,
        ]);

        if (empty($seed_user_ids)) {
            $ref = wp_get_referer();
            $ref = $ref ? add_query_arg('tv_reset', 'none', remove_query_arg(['_wpnonce', 'action'], $ref))
                : admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');
                $ref = add_query_arg('tvsection', 'tools', $ref);

            wp_safe_redirect($ref);
            exit;
        }

        $deleted_users    = 0;
        $deleted_requests = 0;

        foreach ($seed_user_ids as $uid) {
            // 1) Delete all verification_request posts tied to this user
            $q = new \WP_Query([
                'post_type'      => CPT::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    ['key' => '_vp_user_id', 'value' => $uid, 'compare' => '='],
                ],
            ]);

            if (!empty($q->posts)) {
                foreach ($q->posts as $pid) {
                    // Allow force-delete
                    if (current_user_can('delete_post', $pid)) {
                        wp_delete_post((int)$pid, true);
                        $deleted_requests++;
                    }
                }
            }

            // 2) Delete the user (skip current user + admins for safety)
            if ((int) get_current_user_id() === (int) $uid) {
                // Do not delete yourself; just unflag
                delete_user_meta($uid, '_yardlii_tv_seed_user');
                continue;
            }

            $u = get_userdata($uid);
            $roles = $u ? (array) $u->roles : [];
            if (in_array('administrator', $roles, true)) {
                // Never delete admins; just unflag
                delete_user_meta($uid, '_yardlii_tv_seed_user');
                continue;
            }

            // Delete user; no reassignment needed because these requests are already removed
            if (function_exists('wp_delete_user')) {
                wp_delete_user($uid);
                $deleted_users++;
            } else {
                // Fallback: just unflag
                delete_user_meta($uid, '_yardlii_tv_seed_user');
            }
        }

        $ref = wp_get_referer();
        $ref = $ref ?: admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');
        $ref = add_query_arg('tvsection', 'tools', $ref);
        $ref = $ref ? remove_query_arg(['_wpnonce', 'action'], $ref) : admin_url('admin.php?page=yardlii-core-settingss&tab=trust-verification');
        $ref = add_query_arg([
            'tv_reset'   => 'ok',
            'tv_users'   => $deleted_users,
            'tv_requests' => $deleted_requests,
        ], $ref);
$ref = add_query_arg('tvsection', 'tools', $ref);

        wp_safe_redirect($ref);
        exit;
    }
}
