<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\UI;

/**
 * Trust & Verification — Admin Assets Bootstrap
 * - Enqueues TV CSS/JS only on YARDLII Core Settings screen.
 * - Provides YardliiTV globals (AJAX + REST + nonces).
 */
final class AdminPage
{
    public const TAB_SLUG       = 'trust-verification';
    private const STYLE_HANDLE  = 'yardlii-tv';
    private const SCRIPT_HANDLE = 'yardlii-tv';

    public function __construct(
        private string $pluginFile,
        private ?string $version
    ) {}

    /** Public: register hooks */
    public function register(): void
    {
        // Only handle assets; panel is rendered from SettingsPageTabs
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /* =========================================
     *  Enqueue
     * =======================================*/
    /**
     * @param string $hook Current admin screen hook suffix
     */
    public function enqueueAssets(string $hook): void
    {
        if (!$this->isOurSettingsPage($hook)) {
            return;
        }
        // inside enqueueAssets(), right after the isOurSettingsPage() check
if (class_exists('\Yardlii\Core\Features\TrustVerification\Caps')) {
    if (! current_user_can(\Yardlii\Core\Features\TrustVerification\Caps::MANAGE)) {
        return; // optionally skip enqueuing for users who can't manage TV
    }
} else {
    if (! current_user_can('manage_options')) {
        return;
    }
}


        // Editor APIs for wp.editor.initialize() (TinyMCE/Quicktags)
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor(); // registers/enqueues 'wp-editor'
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        $baseUrl = plugin_dir_url($this->pluginFile);
        $baseDir = plugin_dir_path($this->pluginFile);

        $cssRel = 'assets/admin/css/trust-verification.css';
        $jsRel  = 'assets/admin/js/admin-tv.js';

        $verCss = $this->assetVersion($baseDir . $cssRel);
        $verJs  = $this->assetVersion($baseDir . $jsRel);

        /* --- CSS --- */
        wp_enqueue_style(
            self::STYLE_HANDLE,
            $baseUrl . $cssRel,
            [],
            $verCss
        );

        /* --- JS (with deps) --- */
        // We must wait for the scripts loaded by wp_enqueue_editor()
        $deps = ['jquery', 'jquery-ui-sortable', 'editor', 'quicktags'];

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            $baseUrl . $jsRel,
            $deps,
            $verJs,
            true
        );

        /* --- Data for JS --- */
wp_localize_script(self::SCRIPT_HANDLE, 'YardliiTV', [
    'ajax'          => admin_url('admin-ajax.php'),
    'noncePreview'  => wp_create_nonce('yardlii_tv_preview'),
    'nonceSend'     => wp_create_nonce('yardlii_tv_send_test'),
    'nonceHistory'  => wp_create_nonce('yardlii_tv_history'), 
    'restRoot'      => esc_url_raw(rest_url()),
    'restNonce'     => wp_create_nonce('wp_rest'),
]);

    }

    /* =========================================
     *  Helpers
     * =======================================*/
    /**
     * True only on the YARDLII Core Settings screen.
     */
    private function isOurSettingsPage(string $hook): bool
    {
        // 1) Fast path: hook contains our slug
        if (strpos($hook, 'yardlii-core-settings') !== false) {
            return true;
        }

        // 2) URL param fallback
        if (isset($_GET['page']) && $_GET['page'] === 'yardlii-core-settings') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return true;
        }

        // 3) Screen object fallback
        if (function_exists('get_current_screen')) {
            $scr = get_current_screen();
            if ($scr && !empty($scr->id) && strpos($scr->id, 'yardlii-core-settings') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Version from filemtime when possible; falls back to $this->version.
     */
    private function assetVersion(string $absPath): string|bool
    {
        if (is_file($absPath)) {
            $mt = @filemtime($absPath);
            if (is_int($mt)) {
                return (string) $mt;
            }
        }
        return $this->version ?: false;
    }
}
