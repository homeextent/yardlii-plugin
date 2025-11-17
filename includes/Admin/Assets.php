<?php
namespace Yardlii\Core\Admin;

class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // ✅ Removed runtime diagnostic injection hook
    }

    public function enqueue_admin_assets(): void
    {
        $screen = get_current_screen();

        // Only load on YARDLII Core settings page
        if (empty($screen) || strpos($screen->id, 'yardlii-core') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'yardlii-admin',
            plugins_url('/assets/css/admin.css', YARDLII_CORE_FILE),
            [],
            YARDLII_CORE_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'yardlii-admin',
            plugins_url('/assets/js/admin.js', YARDLII_CORE_FILE),
            ['jquery'],
            YARDLII_CORE_VERSION,
            true
        );

       // Dynamically get all registered special handlers
$acf_sync = new \Yardlii\Core\Features\ACFUserSync();
$registered_handlers = $acf_sync->get_registered_special_handlers();

// Build the dropdown options from the registry
$special_options = ['' => __('None', 'yardlii-core')];
foreach ($registered_handlers as $key => $handler) {
    $special_options[$key] = $handler['label'];
}

wp_localize_script('yardlii-admin', 'YARDLII_ADMIN', [
    'nonce'            => wp_create_nonce('yardlii_admin_nonce'),
    'nonce_badge_sync' => wp_create_nonce('yardlii_diag_badge_sync_nonce'),
    'nonce_search_cache' => wp_create_nonce('yardlii_diag_search_cache_nonce'),
    'ajaxurl'          => admin_url('admin-ajax.php'),
    'specialOptions'   => $special_options,
]);

    }
}
