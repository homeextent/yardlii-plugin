<?php
namespace Yardlii\Core\Features;

if (!defined('ABSPATH')) exit;

/**
 * Ensures configured custom roles exist with desired caps on each load (idempotent).
 * Deletions are handled at save-time in the sanitize callback.
 */
class CustomUserRoles
{
    public function register(): void
    {
        add_action('init', [$this, 'sync_roles'], 20);
    }


    public static function sanitize_settings($raw)
    {
        // Master off? Keep previous value, do nothing.
$master = (bool) get_option('yardlii_enable_role_control', false);
if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
    $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
}
if (!$master) {
    return get_option('yardlii_custom_roles', []);
}


        // 1) Permission guard
        if (!current_user_can('manage_options')) {
            return get_option('yardlii_custom_roles', []);
        }

        // === PATCH 1: START ===========================================
    // 1a) MASTER switch guard + UI notice
    $master = (bool) get_option('yardlii_enable_role_control', false);
    if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
        $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
    }
    if (!$master) {
        \add_settings_error(
            'yardlii_role_control_group',
            'yardlii_master_off',
            __('Role Control is disabled. Enable it in Advanced → Feature Flags to manage custom roles.', 'yardlii-core'),
            'warning'
        );
        return get_option('yardlii_custom_roles', []);
    }

    // 1b) Per-feature guard + UI notice (prevents accidental mutations)
    $enabled_post = isset($_POST['yardlii_enable_custom_roles']) && $_POST['yardlii_enable_custom_roles'];
    $enabled = $enabled_post ? true : (bool) get_option('yardlii_enable_custom_roles', true);
    if (!$enabled) {
        \add_settings_error(
            'yardlii_role_control_group',
            'yardlii_cur_off',
            __('Custom User Roles is disabled. Enable it to create, update, or remove roles.', 'yardlii-core'),
            'info'
        );
        return get_option('yardlii_custom_roles', []);
    }
    // === PATCH 1: END =============================================

        // 2) If feature disabled, keep existing (prevents accidental deletions)
        $enabled_post = isset($_POST['yardlii_enable_custom_roles']) && $_POST['yardlii_enable_custom_roles'];
        $enabled = $enabled_post ? true : (bool) get_option('yardlii_enable_custom_roles', true);
        if (!$enabled) {
            return get_option('yardlii_custom_roles', []);
        }

        // 3) Main logic (clean, create/update, delete + reassign)
        $prev        = get_option('yardlii_custom_roles', []);
        $prev_slugs  = is_array($prev) ? array_keys($prev) : [];
        $reserved    = ['administrator','editor','author','contributor','subscriber'];
        $clean       = [];

        if (!is_array($raw)) { $raw = []; }

        foreach ($raw as $row) {
            $slug = isset($row['slug']) ? sanitize_key($row['slug']) : '';
            if ($slug === '' || in_array($slug, $reserved, true)) continue;

            $label = (!empty($row['label'])) ? sanitize_text_field($row['label']) : $slug;

            $base  = isset($row['base_role']) ? sanitize_key($row['base_role']) : '';
            $base_role = $base ? get_role($base) : null;

            // Start with base caps
            $caps = $base_role ? (array) $base_role->capabilities : [];

            // Extra caps (comma/space separated)
            $extra = isset($row['extra_caps']) ? (string) $row['extra_caps'] : '';
            if ($extra !== '') {
                $parts = preg_split('/[\s,]+/', $extra);
                foreach ($parts as $cap) {
                    $cap = sanitize_key($cap);
                    if ($cap !== '') $caps[$cap] = true;
                }
            }

            // Create/update role
            $existing = get_role($slug);
            if (!$existing) {
                add_role($slug, $label, $caps);
            } else {
                $existing_caps = (array) $existing->capabilities;
                $to_add    = array_diff_key($caps, $existing_caps);
                $to_remove = array_diff_key($existing_caps, $caps);

                foreach (array_keys($to_add) as $c)    { $existing->add_cap($c); }
                foreach (array_keys($to_remove) as $c) { $existing->remove_cap($c); }

                // Update display name
                global $wp_roles;
                if (isset($wp_roles->roles[$slug])) {
                    $wp_roles->roles[$slug]['name'] = $label;
                    $wp_roles->role_names[$slug]    = $label;
                }
            }

            // Save minimal config (extra-only vs base)
            $base_caps  = $base_role ? (array) $base_role->capabilities : [];
            $extra_only = array_diff_key($caps, $base_caps);

            $clean[$slug] = [
                'label'      => $label,
                'base_role'  => $base,
                'extra_caps' => implode(',', array_keys($extra_only)),
            ];
        }

        // Deletions
        $now_slugs = array_keys($clean);
        $to_delete = array_diff($prev_slugs, $now_slugs);

        foreach ($to_delete as $slug) {
            if (in_array($slug, $reserved, true)) continue;

            $users = get_users(['role' => $slug, 'fields' => 'ID']);
            foreach ($users as $uid) {
                $u = new \WP_User($uid);
                if (count((array) $u->roles) <= 1) {
                    $u->set_role('subscriber');
                } else {
                    $u->remove_role($slug);
                }
            }
            remove_role($slug);
        }

        return $clean;
    }

    // Optional runtime sync (keeps roles aligned on page loads)
    public function sync_roles(): void
    {
        if (!(bool) get_option('yardlii_enable_custom_roles', true)) return;

        $configured = (array) get_option('yardlii_custom_roles', []);
        if (!$configured) return;

        foreach ($configured as $slug => $data) {
            $slug  = sanitize_key($slug);
            if ($slug === '') continue;

            $label = isset($data['label']) ? sanitize_text_field($data['label']) : $slug;
            $base  = isset($data['base_role']) ? sanitize_key($data['base_role']) : '';
            $extra = isset($data['extra_caps']) ? (string) $data['extra_caps'] : '';

            $caps = [];
            $base_role = $base ? get_role($base) : null;
            if ($base_role) $caps = (array) $base_role->capabilities;

            if ($extra !== '') {
                $parts = preg_split('/[\s,]+/', $extra);
                foreach ($parts as $cap) {
                    $cap = sanitize_key($cap);
                    if ($cap !== '') $caps[$cap] = true;
                }
            }

            $existing = get_role($slug);
            if (!$existing) {
                add_role($slug, $label, $caps);
            } else {
                $existing_caps = (array) $existing->capabilities;
                $to_add    = array_diff_key($caps, $existing_caps);
                $to_remove = array_diff_key($existing_caps, $caps);
                foreach (array_keys($to_add) as $c)    { $existing->add_cap($c); }
                foreach (array_keys($to_remove) as $c) { $existing->remove_cap($c); }

                global $wp_roles;
                if (isset($wp_roles->roles[$slug])) {
                    $wp_roles->roles[$slug]['name'] = $label;
                    $wp_roles->role_names[$slug]    = $label;
                }
            }
        }
    }


    
}
