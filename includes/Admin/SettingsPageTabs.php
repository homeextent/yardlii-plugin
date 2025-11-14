<?php
namespace Yardlii\Core\Admin;

use Yardlii\Core\Helpers\Notices;

defined('ABSPATH') || exit;

/**
 * YARDLII Core Settings — Tabs Shell
 * - Adds menu + renders tabbed UI
 * - Registers settings grouped by feature
 * - Renders inline, group-scoped notices (no global admin rail)
 * - Hides global notices on tabs that render inline (TV/Advanced)
 */
final class SettingsPageTabs
{
    /** Group keys (rendered via settings_errors where applicable) */
    private const GROUP_DEBUG          = 'yardlii_debug_group';
    private const GROUP_FEATURE_FLAGS  = 'yardlii_feature_flags_group';

    private const GROUP_SEARCH         = 'yardlii_search_group';
    private const GROUP_GOOGLE_MAP     = 'yardlii_google_map_group';
    private const GROUP_FEATURED_IMAGE = 'yardlii_featured_image_group';
    private const GROUP_GENERAL        = 'yardlii_general_group';

    private const GROUP_ROLE_CONTROL   = 'yardlii_role_control_group';
    private const GROUP_ROLE_BADGES    = 'yardlii_role_control_badges_group';

    // If TV has its own settings groups, render them inline on the TV tab as well:
    private const GROUP_TV_GLOBAL      = 'yardlii_tv_global_group';
    private const GROUP_TV_FORM_CFG    = 'yardlii_tv_form_configs_group';

    /* =========================================
     * Bootstrap
     * =======================================*/
    public function register(): void
{
    add_action('admin_menu',  [$this, 'add_menu']);
    add_action('admin_init',  [$this, 'register_settings']);

    // Run before any admin_notices on our settings page
    add_action('load-settings_page_yardlii-core-settings', [$this, 'preemptNoticesForPage'], 0);

    // (Optional defense-in-depth)
    add_filter('get_settings_errors', [$this, 'filterTvGroupsFromGlobal'], 5, 2);
}



    /** Hide core/global settings_errors banners on our settings page only. */
    public function suppressGlobalSettingsErrorsOnOurPage(): void
    {
        if (! $this->isOurSettingsPage()) return;
        remove_action('admin_notices', 'settings_errors');
        remove_action('network_admin_notices', 'settings_errors');
    }

    private function currentUserCanTv(): bool
{
    if (class_exists('\Yardlii\Core\Features\TrustVerification\Caps')) {
        return current_user_can(\Yardlii\Core\Features\TrustVerification\Caps::MANAGE);
    }
    // Fallback: site admins
    return current_user_can('manage_options');
}


    private function isOurSettingsPage(): bool
    {
        if (!is_admin()) return false;
        return (isset($_GET['page']) && $_GET['page'] === 'yardlii-core-settings');
    }

    private function isTvTab(): bool
{
    if (! $this->isOurSettingsPage()) return false;
    if (!isset($_GET['tab']) || sanitize_key($_GET['tab']) !== 'trust-verification') return false;
    return $this->currentUserCanTv();
}


    /**
     * Runs before most notices are printed. Remove our TV groups so they
     * don't appear in the global notice area on the TV tab.
     */
    public function earlyHideTvNotices(): void
    {
        if (!$this->isTvTab()) return;

        // Stop the generic settings_errors() printer entirely for this tab.
        remove_action('admin_notices', 'settings_errors');
        remove_action('network_admin_notices', 'settings_errors');

        // And scrub our groups from the pool in case some theme/plugin echoes them manually.
        global $wp_settings_errors;
        if (is_array($wp_settings_errors)) {
            $wp_settings_errors = array_values(array_filter(
                $wp_settings_errors,
                static function ($e) {
                    $s = isset($e['setting']) ? (string) $e['setting'] : '';
                    return $s !== self::GROUP_TV_GLOBAL && $s !== self::GROUP_TV_FORM_CFG;
                }
            ));
        }
    }

    public function preemptNoticesForPage(): void
{
    if (! $this->isOurSettingsPage()) return;

    // Stop the generic global printers for this page
    remove_action('admin_notices', 'settings_errors');
    remove_action('network_admin_notices', 'settings_errors');

    // Block anyone else from seeing TV groups unless we set the allow flag
    add_filter('get_settings_errors', [$this, 'filterTvGroupsFromGlobal'], 0, 2);
}


    


    public function preemptTvNotices(): void
{
    if (! $this->isOurSettingsPage()) {
        return;
    }

    // Stop the core/global printer for this page load (we’ll show scoped banners ourselves).
    remove_action('admin_notices', 'settings_errors');
    remove_action('network_admin_notices', 'settings_errors');

    // Scrub our TV groups from any later global calls to get_settings_errors().
    add_filter('get_settings_errors', [$this, 'filterTvGroupsFromGlobal'], 0, 2);
}



    
    public function filterTvGroupsFromGlobal(array $errors, $setting)
{
    if (! $this->isOurSettingsPage()) return $errors;

    // Allow only while we explicitly render inline banners
    if (!empty($GLOBALS['yardlii_tv_allow_inline_errors'])) {
        return $errors;
    }

    $blocked = [ self::GROUP_TV_GLOBAL, self::GROUP_TV_FORM_CFG ];

    return array_values(array_filter(
        $errors,
        static function ($e) use ($blocked) {
            $s = isset($e['setting']) ? (string) $e['setting'] : '';
            return ! in_array($s, $blocked, true);
        }
    ));
}





    /* =========================================
     * Settings Registration (grouped)
     * =======================================*/

    /** Helper: sanitizer that emits a group-scoped success notice once per submit */
    private static function success_notifier(string $group, callable $sanitize = null): callable
    {
        return static function ($v) use ($group, $sanitize) {
            $val = is_callable($sanitize) ? $sanitize($v) : $v;

            if (isset($_POST['option_page']) && $_POST['option_page'] === $group) {
                static $notified = [];
                if (empty($notified[$group])) {
                    add_settings_error($group, "{$group}_saved", __('Settings saved.', 'yardlii-core'), 'updated');
                    $notified[$group] = true;
                }
            }
            return $val;
        };
    }

    public function register_settings(): void
    {
        $this->register_debug_settings();
        $this->register_feature_flags_settings();

        // Search & location
        $this->register_search_settings();

        // Google Maps
        $this->register_google_map_settings();

        // Featured image automation
        $this->register_featured_image_settings();

        // WPUF (in General)
        register_setting(self::GROUP_GENERAL, 'yardlii_enable_wpuf_dropdown', [
            'sanitize_callback' => self::success_notifier(self::GROUP_GENERAL, static fn($v)=>(bool) $v),
        ]);
	// WPUF: Target Pages Configuration
        register_setting(self::GROUP_GENERAL, 'yardlii_wpuf_target_pages', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'submit-a-post', // Keep current behavior as default
        ]);
        // WPUF: Card Layout Toggle
        register_setting(self::GROUP_GENERAL, 'yardlii_wpuf_card_layout', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
        // WPUF: Modern Uploader Skin
        register_setting(self::GROUP_GENERAL, 'yardlii_wpuf_modern_uploader', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        // Role Control (main group)
        $this->register_role_control_settings();

        // Role Control → Badge Assignment (isolated group)
        $this->register_role_badge_settings();

        // (Optional) Trust & Verification own groups, if panel registers options:
        // register_setting(self::GROUP_TV_GLOBAL, 'yardlii_tv_global_option', ['sanitize_callback' => self::success_notifier(self::GROUP_TV_GLOBAL)]);
        // register_setting(self::GROUP_TV_FORM_CFG,'yardlii_tv_form_cfg',    ['sanitize_callback' => self::success_notifier(self::GROUP_TV_FORM_CFG)]);
    }

    /** Debug mode: group-scoped success notice + toggle */
    private function register_debug_settings(): void
    {
        register_setting(self::GROUP_DEBUG, 'yardlii_debug_mode', [
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => self::success_notifier(self::GROUP_DEBUG, static fn($v)=>(bool)$v),
        ]);
	register_setting(self::GROUP_DEBUG, 'yardlii_remove_data_on_delete', [
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => self::success_notifier(self::GROUP_DEBUG, static fn($v) => (bool)$v),
		]);
    }

    /** Feature Flags: group-scoped success + toggles */
    private function register_feature_flags_settings(): void
    {
        $cb = self::success_notifier(self::GROUP_FEATURE_FLAGS);
        
        register_setting(self::GROUP_FEATURE_FLAGS, 'yardlii_enable_trust_verification', ['sanitize_callback' => self::success_notifier(self::GROUP_FEATURE_FLAGS, static fn($v)=>(bool)$v)]);
        register_setting(self::GROUP_FEATURE_FLAGS, 'yardlii_enable_role_control',       ['sanitize_callback' => self::success_notifier(self::GROUP_FEATURE_FLAGS, static fn($v)=>(bool)$v)]);
    }

    /** Search & Location */
    private function register_search_settings(): void
    {
        $N = self::success_notifier(self::GROUP_SEARCH);

        register_setting(self::GROUP_SEARCH, 'yardlii_primary_taxonomy',      ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_primary_label',         ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_primary_facet',         ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_secondary_taxonomy',    ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_secondary_label',       ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_secondary_facet',       ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_homepage_search_debug', ['sanitize_callback' => $N]);

        register_setting(self::GROUP_SEARCH, 'yardlii_location_facet',        ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_location_label',        ['sanitize_callback' => $N]);
        register_setting(self::GROUP_SEARCH, 'yardlii_enable_location_search',[
            'sanitize_callback' => self::success_notifier(self::GROUP_SEARCH, static fn($v)=>(bool)$v),
        ]);
    }

    /** Google Maps */
    private function register_google_map_settings(): void
    {
        $N = self::success_notifier(self::GROUP_GOOGLE_MAP);
        register_setting(self::GROUP_GOOGLE_MAP, 'yardlii_google_map_key', ['sanitize_callback' => $N]);
        register_setting(self::GROUP_GOOGLE_MAP, 'yardlii_map_controls',   ['sanitize_callback' => $N]);
    }

    /** Featured Image automation */
    private function register_featured_image_settings(): void
    {
        $N = self::success_notifier(self::GROUP_FEATURED_IMAGE);
        register_setting(self::GROUP_FEATURED_IMAGE, 'yardlii_featured_image_field', ['sanitize_callback' => $N]);
        register_setting(self::GROUP_FEATURED_IMAGE, 'yardlii_listing_form_id',      ['sanitize_callback' => $N]);
        register_setting(self::GROUP_FEATURED_IMAGE, 'yardlii_featured_image_debug', ['sanitize_callback' => $N]);
    }

    /** Role Control main */
    private function register_role_control_settings(): void
    {
        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_enable_role_control_submit', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, static fn($v)=>(bool)$v),
        ]);

        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_role_control_allowed_roles', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, static function ($v) {
                if (!is_array($v)) $v = [];
                $v = array_map('sanitize_text_field', $v);
                $editable = array_keys(get_editable_roles());
                return array_values(array_intersect($v, $editable));
            }),
        ]);

        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_role_control_denied_action', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, static function ($v) {
                $v = sanitize_text_field($v);
                return in_array($v, ['redirect_login', 'message'], true) ? $v : 'message';
            }),
        ]);

        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_role_control_denied_message', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, 'sanitize_textarea_field'),
        ]);

        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_role_control_target_page', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, 'sanitize_text_field'),
        ]);

        // Custom roles (delegated sanitizer)
        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_enable_custom_roles', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, static fn($v)=>(bool)$v),
        ]);
        register_setting(self::GROUP_ROLE_CONTROL, 'yardlii_custom_roles', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_CONTROL, ['Yardlii\\Core\\Features\\CustomUserRoles', 'sanitize_settings']),
        ]);
    }

    /** Role badges (isolated) */
    private function register_role_badge_settings(): void
    {
        register_setting(self::GROUP_ROLE_BADGES, 'yardlii_enable_badge_assignment', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_BADGES, static fn($v)=>(bool)$v),
        ]);
        register_setting(self::GROUP_ROLE_BADGES, 'yardlii_rc_badges', [
            'sanitize_callback' => self::success_notifier(self::GROUP_ROLE_BADGES, ['Yardlii\\Core\\Features\\RoleControlBadgeAssignment', 'sanitize_settings']),
            'default'           => ['map' => [], 'meta_key' => 'user_badge', 'fallback_field' => ''],
        ]);
    }

    /* =========================================
     * Menu + Page
     * =======================================*/
    public function add_menu(): void
    {
        add_options_page(
            __('YARDLII Core Settings', 'yardlii-core'),
            __('YARDLII Core', 'yardlii-core'),
            'manage_options',
            'yardlii-core-settings',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) return;

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        // If a user tries to deep-link to TV without the cap, bounce to General with a friendly banner
if ($active_tab === 'trust-verification' && ! $this->currentUserCanTv()) {
    echo '<div class="notice notice-error is-dismissible" style="margin:12px 0;"><p>'
       . esc_html__('You do not have permission to view Trust & Verification.', 'yardlii-core')
       . '</p></div>';
    $active_tab = 'general';
}


        // Prevent global settings_errors() on TV (and optionally Advanced)
        if ($active_tab === 'trust-verification') {
            remove_action('admin_notices', 'settings_errors');
            remove_action('network_admin_notices', 'settings_errors');

            echo '<style id="yardlii-tv-hide-global-rail">
/* Hide any top-of-page WP notices while on the TV tab */
body.settings_page_yardlii-core-settings #wpbody-content > .notice,
body.settings_page_yardlii-core-settings .wrap > .notice,
body.settings_page_yardlii-core-settings .update-nag {
  display: none !important;
}
</style>';


            // Hide all top-of-page admin notices on TV; we render scoped ones inline.
            echo '<style id="yardlii-hide-core-notices">.wrap > .notice{display:none !important}</style>';

            // Clean the settings-updated param so core doesn’t re-add its green banner.
            if (isset($_GET['settings-updated'])) {
                echo '<script>(function(){try{var u=new URL(location.href);u.searchParams.delete("settings-updated");history.replaceState({},"",u.toString());}catch(e){}})();</script>';
            }
        }

        // Show global success only on tabs that are NOT TV/Advanced
        if (!empty($_GET['settings-updated']) && $active_tab !== 'trust-verification' && $active_tab !== 'advanced') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>'
               . esc_html__('Settings saved successfully.', 'yardlii-core')
               . '</strong></p></div>';
        }

        // Hide core green success on TV and Advanced
        if ($active_tab === 'trust-verification' || $active_tab === 'advanced') {
            echo '<style id="yardlii-hide-core-updated">#setting-error-settings_updated,.wrap>.notice.notice-success{display:none!important}</style>';
            if (isset($_GET['settings-updated'])) {
                echo '<script>(function(){try{var u=new URL(location.href);u.searchParams.delete("settings-updated");history.replaceState({},"",u.toString());}catch(e){}})();</script>';
            }
        }

        // Effective flags (with constant overrides)
        $user_sync_enabled = (bool) get_option('yardlii_enable_acf_user_sync', false);
        if (defined('YARDLII_ENABLE_ACF_USER_SYNC')) {
            $user_sync_enabled = (bool) YARDLII_ENABLE_ACF_USER_SYNC;
        }

        $role_control_master = (bool) get_option('yardlii_enable_role_control', false);
        if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
            $role_control_master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
        }

        // Trust & Verification master flag
$tv_on = (bool) get_option('yardlii_enable_trust_verification', true);
if (defined('YARDLII_ENABLE_TRUST_VERIFICATION')) {
    $tv_on = (bool) YARDLII_ENABLE_TRUST_VERIFICATION; // constant wins
}


$tv_cap_ok  = $this->currentUserCanTv();
$tv_visible = $tv_on && $tv_cap_ok;



        echo '<div class="wrap yardlii-wrap">';

        // Header
        echo '<div class="yardlii-header">';
        echo '<img class="yardlii-logo" src="' . esc_url(plugins_url('/assets/images/logo.png', YARDLII_CORE_FILE)) . '" alt="YARDLII" width="40" height="40" />';
        echo '<h1 class="yardlii-title">' . esc_html__('YARDLII Core Settings', 'yardlii-core') . '</h1>';
        echo '</div>';

        // Plugin-scoped queued notices (custom helper, optional)
        if (class_exists(Notices::class)) {
            Notices::render();
        }

        // Top-level tabs
        ?>
        <nav class="yardlii-tabs" role="tablist" data-scope="main">
            <button type="button" class="yardlii-tab active" data-tab="general" aria-selected="true">🗺️ General</button>
            <button type="button" class="yardlii-tab" data-tab="role-control" aria-selected="false">🛡️ Role Control</button>
            <?php if ($tv_visible): ?>
    <button id="yardlii-tab-btn-trust-verification"
            class="yardlii-tab"
            data-tab="trust-verification"
            aria-controls="yardlii-tab-trust-verification"
            aria-selected="false">🤝 Trust & Verification</button>
<?php endif; ?>

            
            <button type="button" class="yardlii-tab" data-tab="advanced" aria-selected="false">⚙️ Advanced</button>
        </nav>

        <!-- General Tab -->
        <section id="yardlii-tab-general" class="yardlii-tabpanel" data-panel="general">
            <?php
            // Render inline notices for the General tab’s groups
            if (function_exists('settings_errors')) {
                settings_errors(self::GROUP_GOOGLE_MAP);
                settings_errors(self::GROUP_FEATURED_IMAGE);
                settings_errors(self::GROUP_SEARCH);
                settings_errors(self::GROUP_GENERAL);
            }
            ?>

            <nav class="yardlii-tabs yardlii-general-subtabs" role="tablist" aria-label="General Sections">
                <button type="button" class="yardlii-tab active" data-gsection="gmap" aria-selected="true">🗺️ Google Map Settings</button>
                <button type="button" class="yardlii-tab"        data-gsection="fimg" aria-selected="false">🖼️ Featured Image Automation</button>
                <button type="button" class="yardlii-tab"        data-gsection="home" aria-selected="false">🔍 Homepage Search</button>
                <button type="button" class="yardlii-tab"        data-gsection="wpuf" aria-selected="false">🔧 WPUF Customisations</button>
            </nav>

            <details class="yardlii-section" id="gsec-gmap" data-gsection="gmap" open>
                <summary>🗺️ Google Map Settings</summary>
                <div class="yardlii-section-content">
                    <div class="yardlii-inner-tabs">
                        <nav class="yardlii-inner-tablist" role="tablist" aria-label="Google Map Settings">
                            <button type="button" class="yardlii-inner-tab active" data-tab="map-api" aria-selected="true">🔑 Google Maps API</button>
                            <button type="button" class="yardlii-inner-tab"        data-tab="map-options" aria-selected="false">⚙️ Map Display Options</button>
                        </nav>

                        <div class="yardlii-inner-tabcontent" data-panel="map-api" role="tabpanel">
                            <?php include __DIR__ . '/views/partials/google-map-key.php'; ?>
                        </div>

                        <div class="yardlii-inner-tabcontent hidden" data-panel="map-options" role="tabpanel">
                            <?php include __DIR__ . '/views/partials/google-map-controls.php'; ?>
                        </div>
                    </div>
                </div>
            </details>

            <details class="yardlii-section" id="gsec-fimg" data-gsection="fimg">
                <summary>🖼️ Featured Image Automation</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/featured-image.php'; ?>
                </div>
            </details>

            <details class="yardlii-section" id="gsec-home" data-gsection="home">
                <summary>🔍 Homepage Search</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/homepage-search.php'; ?>
                </div>
            </details>

            <details class="yardlii-section" id="gsec-wpuf" data-gsection="wpuf">
                <summary>🔧 WPUF Customisations</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/wpuf-customizations.php'; ?>
                </div>
            </details>
        </section>

        <!-- Role Control Tab -->
        <section id="yardlii-tab-role-control"
                 class="yardlii-tabpanel hidden"
                 data-panel="role-control"
                 aria-disabled="<?php echo $role_control_master ? 'false' : 'true'; ?>">

            <?php
            if (function_exists('settings_errors')) {
                settings_errors(self::GROUP_ROLE_CONTROL);
                settings_errors(self::GROUP_ROLE_BADGES);
            }
            ?>

            <?php if (!$role_control_master): ?>
                <div class="notice notice-warning" style="margin:12px 0;">
                    <p><strong><?php esc_html_e('Role Control is disabled.', 'yardlii-core'); ?></strong>
                        <?php esc_html_e('Turn it on in Advanced → Feature Flags to make changes.', 'yardlii-core'); ?></p>
                </div>
                <fieldset disabled aria-disabled="true" class="yardlii-locked">
            <?php endif; ?>

            <nav class="yardlii-tabs yardlii-role-subtabs" role="tablist" aria-label="Role Control Sections">
                <button type="button" class="yardlii-tab"        data-rsection="roles"  aria-selected="false">👥 Custom User Roles</button>
                <button type="button" class="yardlii-tab"        data-rsection="badges" aria-selected="false">🏷️ Badge Assignment</button>
                <button type="button" class="yardlii-tab active" data-rsection="submit" aria-selected="true">🛡️ Submit Access</button>
            </nav>

            <details class="yardlii-section" data-rsection="roles">
                <summary>👥 Custom User Roles</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/role-control-custom-roles.php'; ?>
                </div>
            </details>

            <details class="yardlii-section" data-rsection="badges">
                <summary>🏷️ Badge Assignment</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/role-control-badge-assignment.php'; ?>
                </div>
            </details>

            <details class="yardlii-section" data-rsection="submit" open>
                <summary>🛡️ Submit Access</summary>
                <div class="yardlii-section-content">
                    <?php include __DIR__ . '/views/partials/role-control-submit.php'; ?>
                </div>
            </details>

            <?php if (!$role_control_master): ?>
                </fieldset>
            <?php endif; ?>
        </section>

        <!-- TRUST & VERIFICATION -->
        <?php if ($tv_visible): ?>
<section
    id="yardlii-tab-trust-verification"
    class="yardlii-tabpanel hidden"
    data-panel="trust-verification"
    role="tabpanel"
    aria-labelledby="yardlii-tab-btn-trust-verification"
>
    <?php
    // Hide global rails on TV (only for authorized users)
    remove_action('admin_notices', 'settings_errors');
    remove_action('network_admin_notices', 'settings_errors');

    echo '<style id="yardlii-tv-hide-global-rail">
/* Hide any top-of-page WP notices while on the TV tab */
body.settings_page_yardlii-core-settings #wpbody-content > .notice,
body.settings_page_yardlii-core-settings .wrap > .notice,
body.settings_page_yardlii-core-settings .update-nag { display:none!important; }
</style>';

    if ($tv_on) {
        $panel = plugin_dir_path(YARDLII_CORE_FILE) . 'includes/Admin/views/partials/trust-verification/panel.php';
        if (file_exists($panel)) {
            require $panel;
            echo "\n<!-- TV panel included OK -->\n";
        } else {
            echo '<div class="notice notice-error"><p>'
               . esc_html__('Trust & Verification panel file not found at:', 'yardlii-core') . ' '
               . esc_html($panel) . '</p></div>';
        }
    } else {
        echo '<div class="yardlii-banner yardlii-banner--info yardlii-banner--dismiss" style="margin:1rem 0;">'
           . '<button type="button" class="yardlii-banner__close" data-dismiss="yardlii-banner" aria-label="' . esc_attr__('Dismiss','yardlii-core') . '">×</button>'
           . '<p><strong>' . esc_html__('Trust & Verification is disabled.', 'yardlii-core') . '</strong> '
           . esc_html__('Enable it in Advanced → Feature Flags or via YARDLII_ENABLE_TRUST_VERIFICATION.', 'yardlii-core')
           . '</p></div>';
    }
    ?>
</section>
<?php endif; ?>


       

        <!-- Advanced Tab -->
        
	<section id="yardlii-tab-advanced" class="yardlii-tabpanel hidden" data-panel="advanced">
  <?php
  // Render inline notices for the Advanced tab's groups
  if (function_exists('settings_errors')) {
      settings_errors(self::GROUP_DEBUG);
      settings_errors(self::GROUP_FEATURE_FLAGS);
  }

  // Active subtab
  $adv_section = isset($_GET['advsection']) ? sanitize_key($_GET['advsection']) : 'flags';
  ?>
  <nav class="yardlii-tabs yardlii-advanced-subtabs" role="tablist" aria-label="<?php esc_attr_e('Advanced Sections', 'yardlii-core'); ?>">
    <button type="button" class="yardlii-tab <?php echo $adv_section === 'flags' ? 'active' : ''; ?>" data-asection="flags" aria-selected="<?php echo $adv_section === 'flags' ? 'true' : 'false'; ?>">
      <?php esc_html_e('Feature Flags & Debug', 'yardlii-core'); ?>
    </button>
    <button type="button" class="yardlii-tab <?php echo $adv_section === 'diagnostics' ? 'active' : ''; ?>" data-asection="diagnostics" aria-selected="<?php echo $adv_section === 'diagnostics' ? 'true' : 'false'; ?>">
      <?php esc_html_e('Diagnostics', 'yardlii-core'); ?>
    </button>
  </nav>

  <details class="yardlii-section" id="asec-flags" data-asection="flags" <?php if ($adv_section === 'flags') echo 'open'; ?>>
    <summary><?php esc_html_e('Feature Flags & Debug', 'yardlii-core'); ?></summary>
    <div class="yardlii-section-content">
      <?php
      // Define variables for the partial's scope
      $tv_flag_value  = $tv_on; // $tv_on is defined earlier in this file
      $tv_flag_locked = defined('YARDLII_ENABLE_TRUST_VERIFICATION');
      $group_debug    = self::GROUP_DEBUG;
      $group_flags    = self::GROUP_FEATURE_FLAGS;

      include __DIR__ . '/views/partials/advanced/section-flags.php';
      ?>
    </div>
  </details>

  <details class="yardlii-section" id="asec-diagnostics" data-asection="diagnostics" <?php if ($adv_section === 'diagnostics') echo 'open'; ?>>
    <summary><?php esc_html_e('Diagnostics', 'yardlii-core'); ?></summary>
    <div class="yardlii-section-content">
      <?php include __DIR__ . '/views/partials/advanced/section-diagnostics.php'; ?>
    </div>
  </details>

</section>


        <footer class="yardlii-admin-footer">
            <?php
            echo 'YARDLII Core Functions v' . esc_html(YARDLII_CORE_VERSION) . ' — ';
            echo esc_html__('Last updated:', 'yardlii-core') . ' ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format')));
            ?>
        </footer>

        <?php
        echo '</div>'; // .wrap.yardlii-wrap
    }
}
