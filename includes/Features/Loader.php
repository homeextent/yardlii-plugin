<?php
declare(strict_types=1);

namespace Yardlii\Core\Features;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Boots feature modules and ensures TV settings are registered early
 * so options.php can process them on save.
 */
final class Loader
{
    /** Guard: ensure we only register TV settings once per request */
    private static bool $tvSettingsRegistered = false;

    /**
     * Register Trust & Verification settings EARLY for options.php.
     * Runs in wp-admin (incl. admin-ajax). Safe to call multiple times.
     */
    public static function ensureTvSettingsRegistered(): void
    {
        if (self::$tvSettingsRegistered || ! is_admin()) {
            return;
        }
        self::$tvSettingsRegistered = true;

        // These classes must define:
        //  - yardlii_tv_global_group
        //  - yardlii_tv_form_configs_group
        if (class_exists(\Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings::class)) {
            (new \Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings())->registerSettings();
        }
        if (class_exists(\Yardlii\Core\Features\TrustVerification\Settings\FormConfigs::class)) {
            (new \Yardlii\Core\Features\TrustVerification\Settings\FormConfigs())->registerSettings();
        }
    }

    /**
     * Entry point: wire early settings, then register all feature modules.
     */
    public function register(): void
    {
        // Ensure TV settings are registered as early as possible in this request.
        if (did_action('plugins_loaded')) {
            self::ensureTvSettingsRegistered();
        } else {
            add_action('plugins_loaded', [__CLASS__, 'ensureTvSettingsRegistered'], 0);
        }
        add_action('admin_init', [__CLASS__, 'ensureTvSettingsRegistered'], 0);

        // Register non-TV features.
        $this->register_features();

        // Trust & Verification module behind feature flag/constant override.
        $tv_master = (bool) get_option('yardlii_enable_trust_verification', true);
        if (defined('YARDLII_ENABLE_TRUST_VERIFICATION')) {
            $tv_master = (bool) YARDLII_ENABLE_TRUST_VERIFICATION;
        }

        if (
            $tv_master
            && class_exists(\Yardlii\Core\Features\TrustVerification\Module::class)
        ) {
            (new \Yardlii\Core\Features\TrustVerification\Module(
                defined('YARDLII_CORE_FILE') ? YARDLII_CORE_FILE : __FILE__,
                defined('YARDLII_CORE_VERSION') ? YARDLII_CORE_VERSION : null
            ))->register();
        }
    }

    /**
     * Register other feature modules (gated by their own flags).
     */
    public function register_features(): void
    {
        // === Google Maps ===
        if (class_exists(__NAMESPACE__ . '\\GoogleMapKey')) {
            (new GoogleMapKey())->register();
        }

        // === Featured Image Automation ===
        if (class_exists(__NAMESPACE__ . '\\FeaturedImage')) {
            (new FeaturedImage())->register();
        }

        // === Homepage Search ===
        if (class_exists(__NAMESPACE__ . '\\HomepageSearch')) {
            (new HomepageSearch())->register();
        }

        // === ACF User Sync ===
        $acf_sync_enabled = (bool) get_option('yardlii_enable_acf_user_sync', false);
        if (defined('YARDLII_ENABLE_ACF_USER_SYNC')) {
            $acf_sync_enabled = (bool) YARDLII_ENABLE_ACF_USER_SYNC;
        }
        if ($acf_sync_enabled && class_exists(__NAMESPACE__ . '\\ACFUserSync')) {
            (new ACFUserSync())->register();
        }

        // === WPUF Frontend Enhancements (Dropdown, Cards, etc.) ===
        // We load the class unconditionally if it exists; the class itself checks flags.
        if (class_exists(__NAMESPACE__ . '\\WPUFFrontendEnhancements')) {
            (new WPUFFrontendEnhancements())->register();
        }

        // <=== Unified Featured Listings Logic
        if (class_exists(__NAMESPACE__ . '\\Listings\\FeaturedManager')) {
            (new Listings\FeaturedManager())->register();
        }

        // === Role Control (master + subfeatures) ===
        $rc_master = (bool) get_option('yardlii_enable_role_control', false);
        if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
            $rc_master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
        }

        // Submit Access
        $rc_submit_enabled = $rc_master && (bool) get_option('yardlii_enable_role_control_submit', false);
        if ($rc_submit_enabled && class_exists(__NAMESPACE__ . '\\RoleControlSubmitAccess')) {
            (new RoleControlSubmitAccess())->register();
        }

        // Custom User Roles
        $cur_enabled = $rc_master && (bool) get_option('yardlii_enable_custom_roles', true);
        if ($cur_enabled && class_exists(__NAMESPACE__ . '\\CustomUserRoles')) {
            (new CustomUserRoles())->register();
        }

        // Badge Assignment
        $badge_enabled = $rc_master && (bool) get_option('yardlii_enable_badge_assignment', true);
        if ($badge_enabled && class_exists(__NAMESPACE__ . '\\RoleControlBadgeAssignment')) {
            (new RoleControlBadgeAssignment())->register();
        }
    }

    /**
     * Optional convenience boot if Loader needs to self-wire.
     * Not required if Core::init() calls (new Loader())->register().
     */
    public static function boot(): void
    {
        add_action('plugins_loaded', [__CLASS__, 'onPluginsLoaded'], 5);
    }

    public static function onPluginsLoaded(): void
    {
        (new self())->register();
    }
}
