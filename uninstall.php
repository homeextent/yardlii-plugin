<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://yardlii.com
 * @since      3.5.0
 * @package    Yardlii_Core
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// 1. Check for the safety toggle.
// Default to 'false' (do not purge).
$purge_data = (bool) get_option('yardlii_remove_data_on_delete', false);

// 2. If the toggle is not explicitly enabled, do nothing.
if (!$purge_data) {
	return;
}

// 3. If enabled, proceed with data removal.
global $wpdb;

// 3a. Delete CPT posts (verification_request)
// Note: Using the CPT constant from the plugin is not safe here,
// as the plugin files are already deactivated. We must use the raw string.
$cpt_slug = 'verification_request'; // Based on [cite: 525, 532]
$post_ids = get_posts([
	'post_type'   => $cpt_slug,
	'numberposts' => -1,
	'fields'      => 'ids',
	'post_status' => 'any',
]);

if (!empty($post_ids)) {
	foreach ($post_ids as $id) {
		wp_delete_post($id, true); // true = force delete, bypass trash
	}
}

// 3b. Delete user meta
// We use the meta key from the badge settings default [cite: 825]
$meta_keys = [
	'user_badge', 
	'_vp_user_id', // From [cite: 530]
];
foreach ($meta_keys as $key) {
	// delete_metadata('user', 0, $key, '', true) is correct per [cite: 2232]
	// but $wpdb->delete is more direct for a bulk operation.
	$wpdb->delete($wpdb->usermeta, ['meta_key' => $key]);
}


// 3c. Delete options
$opts_to_delete = [
	// TV settings
	'yardlii_tv_cap_seeded', // [cite: 2212]
	'yardlii_tv_caps_pending_grant', // [cite: 2213]
	\Yardlii\Core\Features\TrustVerification\Settings\FormConfigs::OPT_KEY, // 'yardlii_tv_form_configs'
	
	// Feature flags & settings
	'yardlii_debug_mode', // [cite: 1109]
	'yardlii_enable_acf_user_sync', // [cite: 887]
	'yardlii_enable_trust_verification', // [cite: 896]
	'yardlii_enable_role_control', // [cite: 891]
	'yardlii_enable_wpuf_dropdown', // [cite: 691]
	'yardlii_enable_role_control_submit', // [cite: 780]
	'yardlii_enable_custom_roles', // [cite: 806]
	'yardlii_enable_badge_assignment', // [cite: 818]

	// Role Control settings
	'yardlii_custom_roles', // [cite: 810]
	'yardlii_rc_badges', // [cite: 822]
	'yardlii_role_control_allowed_roles', // [cite: 784]
	'yardlii_role_control_denied_action', // [cite: 791]
	'yardlii_role_control_denied_message', // [cite: 797]
	'yardlii_role_control_target_page', // [cite: 801]

	// Other settings from groups
	'yardlii_primary_taxonomy', // [cite: 731]
	'yardlii_google_map_key', // [cite: 761]
	'yardlii_featured_image_field', // [cite: 770]
	
	// The setting itself
	'yardlii_remove_data_on_delete',
];

foreach ($opts_to_delete as $opt_name) {
	delete_option($opt_name);
}

// Wildcard deletes for any other stragglers [cite: 2220-2226]
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		'yardlii\_%' // Escaped wildcard
	)
);