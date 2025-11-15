<?php
/**
 * Plugin Name:  YARDLII: Core Functions
 * Description:  Centralized modular functionality for the YARDLII platform.
 * Version:      3.9.5
 * Author:       The Innovative Group
 * Text Domain:  yardlii-core
 * License:      GPLv2 or later.
 */

defined('ABSPATH') || exit;

/* =====================================================
 * Constants
 * ===================================================== */
if (!defined('YARDLII_CORE_FILE'))    define('YARDLII_CORE_FILE', __FILE__);
if (!defined('YARDLII_CORE_PATH'))    define('YARDLII_CORE_PATH', plugin_dir_path(__FILE__));
if (!defined('YARDLII_CORE_URL'))     define('YARDLII_CORE_URL',  plugin_dir_url(__FILE__));
if (!defined('YARDLII_CORE_VERSION')) define('YARDLII_CORE_VERSION', '3.9.5');

/* =====================================================
 * i18n
 * ===================================================== */
add_action('plugins_loaded', static function () {
    // Looks for /languages/yardlii-core-LOCALE.mo
    load_plugin_textdomain('yardlii-core', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/* =====================================================
 * Autoloaders
 * ===================================================== */
// Composer (if present)
$autoload_path = YARDLII_CORE_PATH . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

// Lightweight PSR-4 for Yardlii\Core\* (always)
spl_autoload_register(static function ($class) {
    $prefix   = 'Yardlii\\Core\\';
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relative = substr($class, $len);
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

/* =====================================================
 * Trust & Verification caps: grant on activation,
 * deferred grant if class not loaded yet, and optional revoke.
 * ===================================================== */
register_activation_hook(YARDLII_CORE_FILE, static function () {
    if (class_exists('\Yardlii\Core\Features\TrustVerification\Caps')) {
        \Yardlii\Core\Features\TrustVerification\Caps::grantDefault();
        update_option('yardlii_tv_cap_seeded', 1, false); // mark as seeded for this site
        delete_option('yardlii_tv_caps_pending_grant');
        return;
    }
    // Defer once until classes are available
    update_option('yardlii_tv_caps_pending_grant', 1, false);
});

add_action('plugins_loaded', static function () {
    // If activation ran before classes were available, complete the grant here
    if (get_option('yardlii_tv_caps_pending_grant') && class_exists('\Yardlii\Core\Features\TrustVerification\Caps')) {
        \Yardlii\Core\Features\TrustVerification\Caps::grantDefault();
        update_option('yardlii_tv_cap_seeded', 1, false);
        delete_option('yardlii_tv_caps_pending_grant');
    }
});


register_deactivation_hook(YARDLII_CORE_FILE, static function () {
    // Optional: revoke the cap; also clear seed flags
    if (class_exists('\Yardlii\Core\Features\TrustVerification\Caps')) {
        \Yardlii\Core\Features\TrustVerification\Caps::revokeDefault();
    }
    delete_option('yardlii_tv_cap_seeded');
    delete_option('yardlii_tv_caps_pending_grant');
});

// Toggle verbose plugin logs. Set to true in dev, false in prod.
if (!defined('YARDLII_DEBUG')) define('YARDLII_DEBUG', false);

// Convenience logger
if (!function_exists('yardlii_log')) {
    function yardlii_log($msg): void {
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            if (!is_string($msg)) { $msg = wp_json_encode($msg); }
            error_log('[YARDLII] ' . $msg);
        }
    }
}

/* =====================================================
 * Optional feature flags (code-locked defaults)
 * Define in wp-config.php or here BEFORE init if desired.
 * ===================================================== */
// if (!defined('YARDLII_ENABLE_ACF_USER_SYNC')) {
//     define('YARDLII_ENABLE_ACF_USER_SYNC', false);
// }

/* =====================================================
 * Bootstrap Core
 * ===================================================== */
use Yardlii\Core\Core;

add_action('plugins_loaded', static function () {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    if (class_exists(Core::class)) {
        (new Core())->init();
    } else {
        error_log('[YARDLII] Core class not found — check autoloader.');
    }
});
