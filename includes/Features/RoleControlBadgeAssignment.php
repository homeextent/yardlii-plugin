<?php
namespace Yardlii\Core\Features;

if (!defined('ABSPATH')) exit;

/**
 * Maps roles → ACF Options image fields and syncs the chosen image ID
 * into a user meta key (default: user_badge). Triggers on role/profile/login changes.
 */
class RoleControlBadgeAssignment
{
    public const ENABLE_OPTION   = 'yardlii_enable_badge_assignment';
    public const OPTION_KEY      = 'yardlii_rc_badges';
    public const DEFAULT_META_KEY = 'user_badge';

    public function register(): void
    {
        // Sync triggers
        add_action('set_user_role',    [$this, 'sync_on_user_event'], 10, 3);
        add_action('profile_update',   [$this, 'sync_on_user_event'], 10, 2);
        add_action('wp_login',         [$this, 'sync_on_login'],      10, 2);
        add_action('add_user_role',    [$this, 'sync_on_user_event'], 10, 2);
        add_action('remove_user_role', [$this, 'sync_on_user_event'], 10, 2);

        // Optional: per-user “Resync Badge” in Users table
        add_filter('user_row_actions', [$this, 'add_resync_link'], 10, 2);
        add_action('admin_init',       [$this, 'handle_manual_resync']);

         self::register_admin_hooks();
         self::register_profile_preview();
         self::register_users_table_column();
    }


    /** Users list table: add "Badge" column (with optional sorting) */
public static function register_users_table_column(): void {
    add_filter('manage_users_columns',        [__CLASS__, 'add_users_badge_column']);
    add_filter('manage_users_custom_column',  [__CLASS__, 'render_users_badge_column'], 10, 3);
    add_filter('manage_users_sortable_columns',[__CLASS__, 'make_users_badge_sortable']);
    add_action('pre_get_users',               [__CLASS__, 'handle_users_badge_sorting']);
    add_action('admin_head-users.php',        [__CLASS__, 'users_badge_column_style']);
}

public static function add_users_badge_column(array $columns): array {
    // Only show when master + feature are on
    if (!self::is_feature_active()) return $columns;

    // Insert after 'name' if present; otherwise append
    $new = [];
    $inserted = false;
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if (!$inserted && 'name' === $key) {
            $new['yl_badge'] = esc_html__('Badge', 'yardlii-core');
            $inserted = true;
        }
    }
    if (!$inserted) {
        $new['yl_badge'] = esc_html__('Badge', 'yardlii-core');
    }
    return $new;
}

public static function render_users_badge_column(string $value, string $column_name, $user_id) {
    if ('yl_badge' !== $column_name) return $value;
    if (!self::is_feature_active())  return $value;

    $opt = (array) get_option(self::OPTION_KEY, []);
    $meta_key = !empty($opt['meta_key']) ? sanitize_key($opt['meta_key']) : self::DEFAULT_META_KEY;
    $img_id = (int) get_user_meta((int) $user_id, $meta_key, true);

    if ($img_id) {
        // Small, round thumbnail for the table
        $img = wp_get_attachment_image(
            $img_id,
            'thumbnail',
            false,
            [
                'style' => 'width:28px;height:28px;border-radius:50%;object-fit:cover;display:block;margin:auto;',
                'alt'   => esc_attr__('User badge', 'yardlii-core'),
            ]
        );
        return $img ?: '—';
    }
    return '—';
}

public static function make_users_badge_sortable(array $columns): array {
    if (!self::is_feature_active()) return $columns;
    // Sort by our virtual key; we’ll map it in pre_get_users
    $columns['yl_badge'] = 'yl_user_badge';
    return $columns;
}

public static function handle_users_badge_sorting(\WP_User_Query $query): void {
    if (!is_admin() || !function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if (!$screen || 'users' !== $screen->id) return;
    if (!self::is_feature_active()) return;

    $orderby = $query->get('orderby');
    if ('yl_user_badge' !== $orderby) return;

    $opt = (array) get_option(self::OPTION_KEY, []);
    $meta_key = !empty($opt['meta_key']) ? sanitize_key($opt['meta_key']) : self::DEFAULT_META_KEY;

    // Sort by meta_value_num because the badge is stored as attachment ID (integer)
    $query->set('meta_key', $meta_key);
    $query->set('orderby',  'meta_value_num');
    // 'order' (ASC/DESC) is handled by WordPress from the request
}

public static function users_badge_column_style(): void {
    if (!self::is_feature_active()) return;
    echo '<style>.column-yl_badge{width:60px;text-align:center;}</style>';
}

/** Helper: is Role Control + Badge feature active? */
private static function is_feature_active(): bool {
    $master = (bool) get_option('yardlii_enable_role_control', false);
    if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
        $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
    }
    if (!$master) return false;
    return (bool) get_option(self::ENABLE_OPTION, true);
}

    /** Settings sanitizer wired from SettingsPageTabs.php */
    public static function sanitize_settings($raw)
{
    // Master OFF? keep previous
    $master = (bool) get_option('yardlii_enable_role_control', false);
    if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
        $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
    }
    if (!$master) {
        return get_option(self::OPTION_KEY, ['map'=>[], 'meta_key'=>self::DEFAULT_META_KEY, 'fallback_field'=>'']);
    }

    // Permission guard
    if (!current_user_can('manage_options')) {
        return get_option(self::OPTION_KEY, ['map'=>[], 'meta_key'=>self::DEFAULT_META_KEY, 'fallback_field'=>'']);
    }

    // Per-feature toggle guard
    $enabled_post = isset($_POST[self::ENABLE_OPTION]) && (int) $_POST[self::ENABLE_OPTION] === 1;
    $enabled = $enabled_post ? true : (bool) get_option(self::ENABLE_OPTION, true);
    if (!$enabled) {
        return get_option(self::OPTION_KEY, ['map'=>[], 'meta_key'=>self::DEFAULT_META_KEY, 'fallback_field'=>'']);
    }

    $out = ['map'=>[], 'meta_key'=>self::DEFAULT_META_KEY, 'fallback_field'=>''];
    if (!is_array($raw)) $raw = [];

    // Meta key + fallback
    $out['meta_key'] = isset($raw['meta_key']) ? sanitize_key($raw['meta_key']) : self::DEFAULT_META_KEY;
    $out['meta_key'] = $out['meta_key'] ?: self::DEFAULT_META_KEY;
    $out['fallback_field'] = isset($raw['fallback_field']) ? sanitize_key($raw['fallback_field']) : '';

    // Build canonical map from either legacy 'map' or new 'rows'
    $map = [];

    // New repeater format: rows[][role|field]
    if (!empty($raw['rows']) && is_array($raw['rows'])) {
        foreach ($raw['rows'] as $row) {
            if (!is_array($row)) continue;
            $role  = isset($row['role'])  ? sanitize_key($row['role'])  : '';
            $field = isset($row['field']) ? sanitize_key($row['field']) : '';
            // ignore empties; first mapping wins to avoid duplicates
            if ($role && $field && !isset($map[$role])) {
                $map[$role] = $field;
            }
        }
    }

    // Legacy format: map[role] = field (keep supporting)
    if (empty($map) && !empty($raw['map']) && is_array($raw['map'])) {
        foreach ($raw['map'] as $role => $field) {
            $role  = sanitize_key($role);
            $field = sanitize_key($field);
            if ($role && $field && !isset($map[$role])) {
                $map[$role] = $field;
            }
        }
    }

    $out['map'] = $map;
    return $out;
}


    /** --- Sync engine --- */
    private function is_enabled(): bool
    {
        // Requires master + per-feature toggle
        $master = (bool) get_option('yardlii_enable_role_control', false);
        if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
            $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
        }
        if (!$master) return false;
        return (bool) get_option(self::ENABLE_OPTION, true);
    }

    public function sync_on_user_event($user_id): void
    {
        if ($this->is_enabled()) $this->sync_user_badge((int) $user_id);
    }

    public function sync_on_login($user_login, $user): void
    {
        if ($this->is_enabled() && $user && isset($user->ID)) {
            $this->sync_user_badge((int) $user->ID);
        }
    }

    private function sync_user_badge(int $user_id): bool
    {
        if (!function_exists('get_field')) return false; // needs ACF Options
        $user = get_user_by('id', $user_id);
        if (!$user) return false;

        $s   = get_option(self::OPTION_KEY, []);
        $map = is_array($s['map'] ?? null) ? $s['map'] : [];
        $meta_key = sanitize_key($s['meta_key'] ?? self::DEFAULT_META_KEY) ?: self::DEFAULT_META_KEY;
        $fallback = sanitize_key($s['fallback_field'] ?? '');

        $field = null;
        foreach ((array) $user->roles as $role) {
            if (!empty($map[$role])) { $field = sanitize_key($map[$role]); break; }
        }
        if (!$field && $fallback) $field = $fallback;

        if (!$field) { delete_user_meta($user_id, $meta_key); return true; }

        $image = get_field($field, 'option'); // ACF Options field
        $image_id = null;
        if (is_numeric($image)) $image_id = (int) $image;
        elseif (is_array($image) && isset($image['ID'])) $image_id = (int) $image['ID'];
        elseif (is_string($image)) $image_id = attachment_url_to_postid($image);

        if ($image_id) { update_user_meta($user_id, $meta_key, $image_id); return true; }
        delete_user_meta($user_id, $meta_key);
        return false;
    }

    /** Users list “Resync Badge” action */
    public function add_resync_link($actions, $user_obj)
    {
        if (!current_user_can('manage_options') || !$this->is_enabled()) return $actions;
        $url = wp_nonce_url(
            add_query_arg(['yl_rc_resync_badge' => $user_obj->ID], admin_url('users.php')),
            'yl_rc_resync_badge_' . $user_obj->ID
        );
        $actions['yl_rc_resync'] = '<a href="'.esc_url($url).'">'.esc_html__('Resync Badge','yardlii-core').'</a>';
        return $actions;
    }

    public function handle_manual_resync(): void
    {
        if (!isset($_GET['yl_rc_resync_badge'])) return;
        $uid = (int) $_GET['yl_rc_resync_badge'];
        if (!$uid || !current_user_can('manage_options')) return;
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'yl_rc_resync_badge_' . $uid)) return;
        $this->sync_user_badge($uid);
        wp_safe_redirect(remove_query_arg(['yl_rc_resync_badge','_wpnonce']));
        exit;
    }

    /** Admin-post for "Resync All Users" button */
    public static function register_admin_hooks(): void {
    add_action('admin_post_yardlii_rc_badges_resync_all', [__CLASS__, 'handle_resync_all']);
    }

    public static function handle_resync_all(): void {
    // Capability + nonce checks
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'yardlii-core'));
    }
    check_admin_referer('yardlii_rc_badges_resync_all');

    // Master + feature toggle guard (matches runtime guards)
    $master = (bool) get_option('yardlii_enable_role_control', false);
    if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
        $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
    }
    $feature = (bool) get_option(self::ENABLE_OPTION, true);

    if (!$master || !$feature) {
        // Nothing to do; bounce back with a notice
        wp_safe_redirect(add_query_arg(['yl_rc_badges_resynced' => '0'], wp_get_referer() ?: admin_url()));
        exit;
    }

    // Chunk through users to avoid timeouts
    $paged = 1;
    $per_page = 500; // adjust if needed
    do {
        $q = new \WP_User_Query([
            'number' => $per_page,
            'paged'  => $paged,
            'fields' => 'ID',
        ]);
        $ids = (array) $q->get_results();
        foreach ($ids as $uid) {
            // Use the same sync the runtime path uses
            (new self())->sync_user_badge((int) $uid);
        }
        $paged++;
        $more = count($ids) === $per_page;
        } while ($more);

    wp_safe_redirect(add_query_arg(['yl_rc_badges_resynced' => '1'], wp_get_referer() ?: admin_url('users.php')));
    exit;
     }

     /** Show current badge on user profile screens */
    public static function register_profile_preview(): void {
    add_action('show_user_profile', [__CLASS__, 'render_profile_badge']);
    add_action('edit_user_profile', [__CLASS__, 'render_profile_badge']);
    }

    public static function render_profile_badge($user): void {
    if (!($user && isset($user->ID))) {
        return;
    }
    // Master + feature check (optional, but nice)
    $master = (bool) get_option('yardlii_enable_role_control', false);
    if (defined('YARDLII_ENABLE_ROLE_CONTROL')) {
        $master = (bool) YARDLII_ENABLE_ROLE_CONTROL;
    }
    if (!$master || !(bool) get_option(self::ENABLE_OPTION, true)) {
        return;
    }

    $opt = (array) get_option(self::OPTION_KEY, []);
    $meta_key = !empty($opt['meta_key']) ? sanitize_key($opt['meta_key']) : self::DEFAULT_META_KEY;
    $img_id = (int) get_user_meta((int) $user->ID, $meta_key, true);

    echo '<h3>' . esc_html__('Badge', 'yardlii-core') . '</h3>';
    echo '<table class="form-table"><tr><th>' . esc_html__('Current Badge', 'yardlii-core') . '</th><td>';
    if ($img_id) {
        echo wp_get_attachment_image($img_id, 'thumbnail');
    } else {
        echo '<em>' . esc_html__('None', 'yardlii-core') . '</em>';
    }
    echo '</td></tr></table>';
    } 
}
