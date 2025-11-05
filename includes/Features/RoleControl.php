<?php
namespace Yardlii\Core\Features;

if (!defined('ABSPATH')) { exit; }

class RoleControl
{
    const OPTION_KEY = 'yardlii_role_control_settings';

    public function register(): void
    {
        add_action('admin_init', [$this, 'ensure_defaults']);
        // If you ever need assets specific to this tab, enqueue here conditionally.
    }

    public function ensure_defaults(): void
    {

        // The Role Control feature owns its own settings group + option
        register_setting(
            'yardlii_role_control_group',
            self::OPTION_KEY,
            [
              'type' => 'array',
              'sanitize_callback' => function($v) {
                  return is_array($v) ? $v : [];
              }
            ]
        );
        $defaults = [
            'tabs' => [
                'general'      => ['administrator','editor'],
                'user-sync'    => ['administrator'],
                'advanced'     => ['administrator'],
                'role-control' => ['administrator'],
            ],
            'general_sections' => [
                'gmap' => ['administrator','editor'],
                'fimg' => ['administrator','editor'],
                'home' => ['administrator','editor'],
                'wpuf' => ['administrator','editor'],
            ],
            'capability' => 'manage_options',
        ];

        // Register the option here (Feature owns its own option).
        register_setting(
            'yardlii_role_control_group',
            self::OPTION_KEY,
            ['type' => 'array', 'sanitize_callback' => function($v){ return is_array($v) ? $v : []; }]
        );

        if (!is_array(get_option(self::OPTION_KEY))) {
            add_option(self::OPTION_KEY, $defaults);
        }
    }

    public static function get_all_roles(): array
    {
        if (!function_exists('wp_roles')) { return []; }
        $roles = wp_roles()->roles ?? [];
        return array_map(fn($r) => $r['name'] ?? '', $roles);
    }

    public static function can_view_tab(string $tab, ?int $user_id = null): bool
    {
        $settings = get_option(self::OPTION_KEY, []);
        $cap      = $settings['capability'] ?? 'manage_options';
        $user_id  = $user_id ?: get_current_user_id();

        if (!user_can($user_id, $cap)) return false;

        $allowed = $settings['tabs'][$tab] ?? [];
        if (empty($allowed)) return true;

        $user  = get_userdata($user_id);
        if (!$user) return false;
        $roles = (array) $user->roles;

        if (in_array('administrator', $roles, true)) return true;
        return (bool) array_intersect($roles, $allowed);
    }

    public static function can_view_general_section(string $section, ?int $user_id = null): bool
    {
        if (!self::can_view_tab('general', $user_id)) return false;

        $settings = get_option(self::OPTION_KEY, []);
        $allowed  = $settings['general_sections'][$section] ?? [];
        if (empty($allowed)) return true;

        $user  = get_userdata($user_id ?: get_current_user_id());
        if (!$user) return false;
        $roles = (array) $user->roles;

        if (in_array('administrator', $roles, true)) return true;
        return (bool) array_intersect($roles, $allowed);
    }
}
