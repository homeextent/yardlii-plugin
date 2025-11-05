<?php
namespace Yardlii\Core\Features;

class GoogleMapKey
{
    const OPTION_KEY = 'yardlii_google_map_key';
    const OPTION_MAP_CONTROLS = 'yardlii_map_controls';

    public function register(): void
    {
        add_action('acf/init', [$this, 'apply_api_key']);
        add_action('wp_ajax_yardlii_test_google_map_key', [$this, 'ajax_test_google_map_key']);
    
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('facetwp_map_init_args', [$this, 'apply_map_controls']);
    }

    public function apply_api_key(): void
    {
        $key = get_option(self::OPTION_KEY);
        if ($key && function_exists('acf_update_setting')) {
            acf_update_setting('google_api_key', $key);
        }
    }

    public function ajax_test_google_map_key(): void
    {
        check_ajax_referer('yardlii_test_google_map_key');
        $key = get_option(self::OPTION_KEY);
        if (empty($key)) {
            wp_send_json(['success' => false, 'message' => '⚠️ No API key stored in settings.']);
        }

        $response = wp_remote_get('https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($key) . '&callback=none');
        if (is_wp_error($response)) {
            wp_send_json(['success' => false, 'message' => '❌ Request failed: ' . esc_html($response->get_error_message())]);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json(['success' => true, 'message' => '✅ API key appears valid and accessible from this domain.']);
        }

        wp_send_json(['success' => false, 'message' => "⚠️ API response code: {$code}. The key may be restricted or invalid for this domain."]);
    }

    public function register_settings(): void
    {
        // Register the map controls option under the same settings group as the API key
        register_setting(
            'yardlii_google_map_group',
            self::OPTION_MAP_CONTROLS,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_map_controls'],
                'default' => []
            ]
        );
    }

    /**
     * Sanitize and normalize map control checkboxes.
     * Expected keys: zoomControl, rotateControl, mapTypeControl, streetViewControl, fullscreenControl, disableDefaultUI
     */
    public function sanitize_map_controls($input)
    {
        $allowed = [
            'zoomControl',
            'rotateControl', // "Camera Control" label maps to rotateControl
            'mapTypeControl',
            'streetViewControl',
            'fullscreenControl',
            'disableDefaultUI'
        ];
        $clean = [];
        if (is_array($input)) {
            foreach ($allowed as $key) {
                $clean[$key] = isset($input[$key]) && (string)$input[$key] === '1' ? 1 : 0;
            }
        } else {
            foreach ($allowed as $key) {
                $clean[$key] = 0;
            }
        }
        return $clean;
    }

    /**
     * Apply saved map controls to FacetWP Maps init args.
     * https://facetwp.com/help-center/developers/filters/facetwp_map_init_args/
     */
    public function apply_map_controls($args)
{
    $controls = get_option(self::OPTION_MAP_CONTROLS, []);
    if (!is_array($controls)) {
        $controls = [];
    }

    $defaults = [
        'zoomControl' => 1,
        'cameraControl' => 1,
        'mapTypeControl' => 1,
        'streetViewControl' => 1,
        'fullscreenControl' => 1,
        'disableDefaultUI' => 0,
    ];

    $controls = array_merge($defaults, $controls);

    if (!isset($args['init'])) {
        $args['init'] = [];
    }

    // Map each control into the correct nested structure
    $args['init']['zoomControl']       = (bool) $controls['zoomControl'];
    $args['init']['cameraControl']     = (bool) $controls['cameraControl']; // matches latest Google Maps versions (v3.60+)
    $args['init']['mapTypeControl']    = (bool) $controls['mapTypeControl'];
    $args['init']['streetViewControl'] = (bool) $controls['streetViewControl'];
    $args['init']['fullscreenControl'] = (bool) $controls['fullscreenControl'];
    $args['init']['disableDefaultUI']  = (bool) $controls['disableDefaultUI'];

    return $args;
}

}
?>