<?php
namespace Yardlii\Core\Features;

/**
 * Handles ACF → User field synchronization and fallback resolution.
 * Uses a registry for dynamic "special handling" logic.
 */
class ACFUserSync
{
    private const OPTION_KEY = 'yardlii_acf_user_sync_settings_v2';

    public function register(): void
    {
        add_action('admin_init', [$this, 'register_setting']);
        add_filter('acf/load_value', [$this, 'handle_acf_load_value'], 10, 3);
        add_action('wp_ajax_yardlii_preview_field', [$this, 'ajax_preview_field']);
    }

    /**
     * Registers the user sync settings with WordPress Settings API.
     */
    public function register_setting(): void
    {
        register_setting(
            self::OPTION_KEY . '_group',
            self::OPTION_KEY,
            ['sanitize_callback' => [$this, 'sanitize_options']]
        );
    }

    /**
     * Define all available "Special Handling" types in one place.
     * Each key maps to a label and a callback.
     */
public function get_registered_special_handlers(): array
{
    return [
        'usermeta' => [
            'label'    => __('User Meta', 'yardlii-core'),
            'callback' => function($author_id, $field) {
                return get_user_meta($author_id, $field, true);
            },
        ],
        'user_data' => [
            'label'    => __('User Data', 'yardlii-core'),
            'callback' => function($author_id, $field) {
                $user = get_userdata($author_id);
                return $user->$field ?? '';
            },
        ],
        'user_registered' => [
            'label'    => __('User Registered (Member Since)', 'yardlii-core'),
            'callback' => function($author_id) {
                $user = get_userdata($author_id);
                if (!$user || empty($user->user_registered)) {
                    return '';
                }

                $settings = get_option(self::OPTION_KEY, []);
                $format_choice = $settings['date_display_format'] ?? 'month_year';

                switch ($format_choice) {
                    case 'full':
                        $formatted = date_i18n(get_option('date_format'), strtotime($user->user_registered));
                        break;
                    case 'year':
                        $formatted = date_i18n('Y', strtotime($user->user_registered));
                        break;
                    default:
                        $formatted = date_i18n('F Y', strtotime($user->user_registered));
                        break;
                }

                return sprintf(__('Member since %s', 'yardlii-core'), $formatted);
            },
        ],
        'user_avatar' => [
            'label'    => __('User Avatar URL', 'yardlii-core'),
            'callback' => function($author_id) {
                $avatar_url = get_avatar_url($author_id, ['size' => 96]);
                return $avatar_url ?: '';
            },
        ],
        // ✅ NEW HANDLER
      'user_company_name' => [
    'label'    => __('User Company Name (ACF)', 'yardlii-core'),
    'callback' => function($author_id) {
        // ✅ Use ACF if available
        if (function_exists('get_field')) {
            $company_name = get_field('yardlii_company_name', 'user_' . $author_id);
            if (!empty($company_name)) {
                return $company_name;
            }
        }

        // ✅ Fallback: use the display name
        $user = get_userdata($author_id);
        if ($user && !empty($user->display_name)) {
            return $user->display_name;
        }

        // ✅ Final fallback: empty string
        return '';
    },
],

// ✅ Instagram URL
'user_instagram_url' => [
    'label'    => __('User Instagram URL (ACF)', 'yardlii-core'),
    'callback' => function($author_id) {
        if (function_exists('get_field')) {
            $instagram = get_field('yardlii_instagram_url', 'user_' . $author_id);
            if (!empty($instagram)) {
                return esc_url($instagram);
            }
        }

        // Fallback: empty
        return '';
    },
],

// ✅ Facebook URL
'user_facebook_url' => [
    'label'    => __('User Facebook URL (ACF)', 'yardlii-core'),
    'callback' => function($author_id) {
        if (function_exists('get_field')) {
            $facebook = get_field('yardlii_facebook_url', 'user_' . $author_id);
            if (!empty($facebook)) {
                return esc_url($facebook);
            }
        }

        // Fallback: empty
        return '';
    },
],

// ✅ User Type
'user_type' => [
    'label'    => __('User Type (ACF)', 'yardlii-core'),
    'callback' => function($author_id) {
        if (function_exists('get_field')) {
            $user_type = get_field('yardlii_user_type', 'user_' . $author_id);
            if (!empty($user_type)) {
                return $user_type;
            }
        }

        // Fallback: use WordPress role label
        $user = get_userdata($author_id);
        if ($user && !empty($user->roles)) {
            $roles = array_map('ucfirst', $user->roles);
            return implode(', ', $roles);
        }

        return '';
    },
],


    ];
}


    /**
     * Sanitize options before saving.
     */
    public function sanitize_options($input): array
    {
        $registered = $this->get_registered_special_handlers();

        $sanitized = [
            'mappings' => [],
            'date_display_format' => sanitize_text_field($input['date_display_format'] ?? 'month_year'),
        ];

        if (empty($input['mappings']) || !is_array($input['mappings'])) {
            return $sanitized;
        }

        foreach ($input['mappings'] as $row) {
            $field = sanitize_text_field($row['field'] ?? '');
            if (!$field) {
                continue;
            }

            $special = sanitize_text_field($row['special'] ?? '');

            $sanitized['mappings'][] = [
                'field'    => $field,
                'fallback' => sanitize_text_field($row['fallback'] ?? ''),
                'special'  => array_key_exists($special, $registered) ? $special : '',
            ];
        }

        return $sanitized;
    }

    /**
     * Hook: acf/load_value — dynamically resolve field values.
     */
    public function handle_acf_load_value($value, $post_id, $field)
    {
        if (empty($field['name']) || !function_exists('get_field')) {
            return $value;
        }

        static $depth = 0;
        if ($depth > 0) {
            return $value;
        }
        $depth++;

        if (!is_numeric($post_id) || get_post_type($post_id) !== 'listings') {
            $depth--;
            return $value;
        }

        $settings = get_option(self::OPTION_KEY, []);
        $mappings = $settings['mappings'] ?? [];

        foreach ($mappings as $map) {
            if ($map['field'] === $field['name']) {
                $resolved = $this->resolve_field_value((int)$post_id, $map, $value);
                $depth--;
                return $resolved;
            }
        }

        $depth--;
        return $value;
    }

    /**
     * Resolve field value dynamically from the registry.
     */
    private function resolve_field_value(int $post_id, array $map, $existing_value = null)
    {
        $author_id = (int) get_post_field('post_author', $post_id);
        $field = $map['field'] ?? '';
        $fallback = $map['fallback'] ?? '';
        $special = $map['special'] ?? '';

        // 1. Keep manually entered values
        if (!empty($existing_value)) {
            return $existing_value;
        }

        // ✅ Dynamic special handling via registry
        $registry = $this->get_registered_special_handlers();

        if (isset($registry[$special]) && is_callable($registry[$special]['callback'])) {
            $result = call_user_func($registry[$special]['callback'], $author_id, $field, $fallback);
            if (!empty($result)) {
                return $result;
            }
        }

        // 2. ACF field on user profile
        if (function_exists('get_field')) {
            $acf_user_field = get_field($field, 'user_' . $author_id);
            if (!empty($acf_user_field)) {
                return $acf_user_field;
            }
        }

        // 3. Fallback value
        return $fallback;
    }

    /**
     * AJAX handler: Preview field resolution results.
     */
    public function ajax_preview_field(): void
    {
        check_ajax_referer('yardlii_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['reason' => 'Unauthorized']);
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $field_name = sanitize_text_field($_POST['field'] ?? '');

        if (!$post_id || !$field_name) {
            wp_send_json_error(['reason' => 'Missing post_id or field']);
        }

        $settings = get_option(self::OPTION_KEY, []);
        $map = null;
        foreach ($settings['mappings'] ?? [] as $m) {
            if ($m['field'] === $field_name) {
                $map = $m;
                break;
            }
        }

        if (!$map) {
            wp_send_json_error(['reason' => 'Mapping not found']);
        }

        $resolved_value = $this->resolve_field_value($post_id, $map);
        $source = $map['special'] ?: 'auto';

        wp_send_json_success([
            'source' => $source,
            'value'  => $resolved_value,
        ]);
    }
}
