<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 * This file is executed by WordPress with core loaded.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only purge if the admin explicitly opted in.
$purge = (bool) get_option('yardlii_remove_data_on_delete', false);
if (!$purge) {
    return;
}

global $wpdb;

/**
 * 1) Delete plugin options (exact matches)
 */
$option_keys = [
    // Trust & Verification options
    'yardlii_tv_global',
    'yardlii_tv_cap_seeded',
    'yardlii_tv_caps_pending_grant',

    // Feature flags and common plugin options (add to this list as needed)
    'yardlii_enable_trust_verification',
    'yardlii_enable_role_control',
    'yardlii_enable_acf_user_sync',
    'yardlii_enable_homepage_search',
    'yardlii_enable_featured_image',
    'yardlii_enable_wpuf_dropdown',

    // Google map, featured image, search, etc. (common top-level options)
    'yardlii_google_map_key',
    'yardlii_map_controls',
    'yardlii_featured_image_field',
    'yardlii_featured_image_debug',
    'yardlii_homepage_search_debug',

    // Role control & badges (add/remove based on your actual keys)
    'yardlii_role_control_allowed_roles',
    'yardlii_role_control_denied_action',
    'yardlii_role_control_denied_message',
    'yardlii_role_control_target_page',
    'yardlii_custom_roles',
    'yardlii_enable_badge_assignment',

    // The uninstall toggle itself
    'yardlii_remove_data_on_delete',
];

foreach ($option_keys as $name) {
    delete_option($name);
}

/**
 * 2) Wildcard removals for families of options
 *    (kept narrow; expand carefully)
 */
$like_patterns = [
    'yardlii_tv_%',
    'yardlii_role_control_%',
    'yardlii_rc_%',
    'yardlii_location_%',
];

foreach ($like_patterns as $like) {
    $sql  = $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like);
    $rows = (array) $wpdb->get_col($sql);
    foreach ($rows as $name) {
        delete_option($name);
    }
}

/**
 * 3) Delete Trust & Verification CPT posts (hard delete)
 *    (Adjust post type slug if yours differs.)
 */
$post_type = 'yardlii_tv_request';
$ids = get_posts([
    'post_type'      => $post_type,
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'cache_results'  => false,
    'suppress_filters' => true,
]);
foreach ($ids as $id) {
    wp_delete_post((int) $id, true);
}

/**
 * 4) Delete user meta used by TV
 */
delete_metadata('user', 0, '_yardlii_tv_seed_user', '', true);

/**
 * NOTE: We intentionally do NOT remove custom roles here.
 * Removing roles on uninstall can surprise site owners.
 * If you want to remove your custom roles, do it only when a SECOND explicit
 * option is enabled, and document it clearly in the README.
 */
