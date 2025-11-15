<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\Listings;

use WP_Query;

/**
 * Unified Featured Listing Manager
 *
 * Merges WPUF "Sticky" logic with Yardlii "is_featured_item" meta logic.
 */
final class FeaturedManager
{
    public const META_KEY = 'is_featured_item';
    public const POST_TYPE = 'listing'; // Adjust if your CPT slug is different

    public function register(): void
    {
        // 1. Admin: Visual Label
        add_filter('display_post_states', [$this, 'addAdminStateLabel'], 10, 2);

        // 2. Admin: Filter Dropdown
        add_action('restrict_manage_posts', [$this, 'addAdminFilterDropdown']);
        add_filter('parse_query', [$this, 'handleAdminFilter']);

        // 3. Frontend: CSS Class Injection (The "No-Shortcode" solution)
        add_filter('post_class', [$this, 'injectPostClass'], 10, 3);

        // 4. Elementor: Custom Query ID
        add_action('elementor/query/yardlii_featured_only', [$this, 'elementorQueryArgs']);
    }

    /**
     * Check if a post is featured by EITHER method.
     */
    public static function isFeatured(int $post_id): bool
    {
        // Condition A: Native Sticky (WPUF method)
        if (is_sticky($post_id)) {
            return true;
        }

        // Condition B: Meta Key (ACF/Admin method)
        // Check for '1' (string) or 1 (int) or true (bool)
        $meta = get_post_meta($post_id, self::META_KEY, true);
        return !empty($meta);
    }

    /**
     * 1. Admin: Add "- Featured" label to the post list
     */
    public function addAdminStateLabel(array $states, \WP_Post $post): array
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $states;
        }

        // If native sticky, WP adds "Sticky" automatically.
        // We only need to add it if it's meta-featured BUT NOT sticky.
        if (!is_sticky($post->ID) && self::isFeatured($post->ID)) {
            $states['featured'] = __('Featured', 'yardlii-core');
        }

        return $states;
    }

    /**
     * 2a. Admin: Render the "Show Featured Only" dropdown
     */
    public function addAdminFilterDropdown(string $post_type): void
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        $current = $_GET['yardlii_featured_filter'] ?? '';
        ?>
        <select name="yardlii_featured_filter">
            <option value=""><?php esc_html_e('All Listings', 'yardlii-core'); ?></option>
            <option value="yes" <?php selected($current, 'yes'); ?>><?php esc_html_e('Show Featured Only', 'yardlii-core'); ?></option>
        </select>
        <?php
    }

    /**
     * 2b. Admin: Handle the filter logic
     */
    public function handleAdminFilter(WP_Query $query): void
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        if (isset($_GET['yardlii_featured_filter']) && $_GET['yardlii_featured_filter'] === 'yes') {
            $this->applyUnifiedQuery($query);
        }
    }

    /**
     * 4. Elementor: Modify query when Query ID = 'yardlii_featured_only'
     */
    public function elementorQueryArgs(WP_Query $query): void
    {
        $this->applyUnifiedQuery($query);
    }

    /**
     * The Core Logic: Merges Sticky IDs + Meta IDs and forces the query to fetch only them.
     */
    private function applyUnifiedQuery(WP_Query $query): void
    {
        // 1. Get all Native Sticky IDs
        $sticky_ids = get_option('sticky_posts');
        if (!is_array($sticky_ids)) {
            $sticky_ids = [];
        }

        // 2. Get all Meta Featured IDs (Performance note: fetching IDs is generally fast)
        $meta_query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::META_KEY,
                    'value'   => '1',
                    'compare' => '=' // ACF true/false usually saves as '1'
                ]
            ]
        ]);
        $meta_ids = $meta_query->posts;

        // 3. Merge and Unique
        $all_featured_ids = array_unique(array_merge($sticky_ids, $meta_ids));

        // 4. Safety: If no featured posts exist, force no results (ID 0)
        if (empty($all_featured_ids)) {
            $all_featured_ids = [0];
        }

        // 5. Apply to Main Query
        $query->set('post__in', $all_featured_ids);
        
        // Important: Reset 'sticky' handling so they don't get double-sorted or ignored
        $query->set('ignore_sticky_posts', 1);
    }

    /**
     * 3. Frontend: Inject CSS Class
     * Allows styling via .yardlii-is-featured { ... }
     */
    public function injectPostClass(array $classes, array $class, int $post_id): array
    {
        if (get_post_type($post_id) === self::POST_TYPE && self::isFeatured($post_id)) {
            $classes[] = 'yardlii-is-featured';
        }
        return $classes;
    }
}