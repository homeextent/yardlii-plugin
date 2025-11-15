<?php
declare(strict_types=1);

namespace Yardlii\Core\Features;

use WP_Post;
use WP_Query;

/**
 * Feature: Featured Listings Logic
 * --------------------------------
 * Unifies "Native Sticky" posts (WPUF) and "Meta Featured" posts (ACF).
 * Includes recursion guards to prevent memory exhaustion.
 */
class FeaturedListings {

    const META_KEY = 'is_featured_item';

    public function register(): void {
        // 1. Sync Meta to Native Sticky on Save
        add_action('save_post', [$this, 'sync_sticky_status'], 20, 3);

        // 2. Admin List Filtering
        add_action('restrict_manage_posts', [$this, 'add_admin_filter']);
        add_action('pre_get_posts', [$this, 'filter_admin_query']);

        // 3. Elementor Loop Grid Query ID: 'featured_listings'
        add_action('elementor/query/featured_listings', [$this, 'filter_elementor_query']);

        // 4. Shortcode for Badge
        add_shortcode('yardlii_featured_badge', [$this, 'render_badge_shortcode']);

        // 5. Force "Sticky" label on CPT Admin List
        add_filter('display_post_states', [$this, 'add_sticky_state'], 10, 2);
    }

    /**
     * Helper: Check if a post is effectively featured (Sticky OR Meta).
     */
    private function is_featured(int $post_id): bool {
        // 1. Check Native
        if (is_sticky($post_id)) {
            return true;
        }

        // 2. Check Meta (ACF/WPUF)
        $val = get_post_meta($post_id, self::META_KEY, true);
        return ($val === '1' || $val === 1 || $val === 'true' || $val === true);
    }

    /**
     * Helper: Get ALL featured IDs (Sticky + Meta) for queries.
     * Optimized to prevent memory bloat and recursion.
     *
     * @return int[]
     */
    private function get_all_featured_ids(): array {
        // 1. Native Sticky IDs
        $sticky_ids = get_option('sticky_posts');
        if (!is_array($sticky_ids)) {
            $sticky_ids = [];
        }

        // 2. Meta Featured IDs
        // Optimization: Lightweight query, no cache, no filters to prevent recursion
        $meta_ids = get_posts([
            'post_type'              => 'listings',
            'numberposts'            => -1,
            'fields'                 => 'ids',
            'meta_key'               => self::META_KEY,
            'meta_value'             => '1',
            'suppress_filters'       => true, // Stop external modification
            'no_found_rows'          => true, // Performance
            'update_post_meta_cache' => false, // Performance
            'update_post_term_cache' => false, // Performance
            'ignore_sticky_posts'    => true,
        ]);

        if (!is_array($meta_ids)) {
            $meta_ids = [];
        }

        // 3. Merge and Unique
        /** @var int[] $merged */
        $merged = array_unique(array_merge($sticky_ids, $meta_ids));
        
        return array_map('intval', $merged);
    }

    /**
     * Sync Logic: Attempt to keep Native Sticky status in sync with Meta.
     */
    public function sync_sticky_status(int $post_id, WP_Post $post, bool $update): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Check $_POST first for immediate responsiveness
        if (isset($_POST[self::META_KEY])) {
            $val = $_POST[self::META_KEY];
        } else {
            $val = get_post_meta($post_id, self::META_KEY, true);
        }

        $is_featured = ($val === '1' || $val === 1 || $val === true || $val === 'true');

        if ($is_featured) {
            stick_post($post_id);
        } else {
            unstick_post($post_id);
        }
    }

    /**
     * Visual Fix: Add "Sticky" label if Sticky OR Meta is true.
     *
     * @param array<string, string> $states
     * @return array<string, string>
     */
    public function add_sticky_state(array $states, WP_Post $post): array {
        if ($post->post_type !== 'listings') {
            return $states;
        }

        if ($this->is_featured($post->ID)) {
            if (!isset($states['sticky'])) {
                $states['sticky'] = __('Sticky', 'yardlii-core');
            }
        }

        return $states;
    }

    /**
     * Add dropdown filter to Admin List
     */
    public function add_admin_filter(string $post_type): void {
        if ($post_type !== 'listings') {
            return;
        }

        $current = isset($_GET['yardlii_filter_featured']) ? sanitize_text_field((string) $_GET['yardlii_filter_featured']) : '';
        ?>
        <select name="yardlii_filter_featured">
            <option value=""><?php _e('All Listings', 'yardlii-core'); ?></option>
            <option value="yes" <?php selected($current, 'yes'); ?>><?php _e('Featured Only', 'yardlii-core'); ?></option>
            <option value="no" <?php selected($current, 'no'); ?>><?php _e('Standard Only', 'yardlii-core'); ?></option>
        </select>
        <?php
    }

    /**
     * Modify Admin Query based on filter
     */
    public function filter_admin_query(WP_Query $query): void {
        // Recursion Guard: Stop infinite loop if get_posts triggers this hook again
        static $is_running = false;
        if ($is_running) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'listings') {
            return;
        }

        if (isset($_GET['yardlii_filter_featured']) && $_GET['yardlii_filter_featured'] !== '') {
            $is_running = true; // Lock

            $featured_ids = $this->get_all_featured_ids();
            $filter = sanitize_text_field((string) $_GET['yardlii_filter_featured']);

            if ($filter === 'yes') {
                if (!empty($featured_ids)) {
                    $query->set('post__in', $featured_ids);
                } else {
                    $query->set('post__in', [0]); // Force empty result
                }
            } elseif ($filter === 'no') {
                if (!empty($featured_ids)) {
                    $query->set('post__not_in', $featured_ids);
                }
            }

            $is_running = false; // Unlock
        }
    }

    /**
     * Elementor Query Filter
     * ID: featured_listings
     */
    public function filter_elementor_query(WP_Query $query): void {
        // Elementor loops can be nested, but get_all_featured_ids is safe now.
        $featured_ids = $this->get_all_featured_ids();

        if (!empty($featured_ids)) {
            $query->set('post__in', $featured_ids);
        } else {
            $query->set('post__in', [0]); 
        }
        
        // Ensure strict inclusion logic by ignoring standard sticky injection
        $query->set('ignore_sticky_posts', true);
    }

    /**
     * Shortcode: [yardlii_featured_badge text="Featured" class="custom-class"]
     * Only renders if the post is truly Sticky (Featured).
     *
     * @param array<string, mixed>|string $atts
     */
    public function render_badge_shortcode($atts): string {
        $a = shortcode_atts([
            'text'  => 'Featured',
            'class' => '',
            'style' => 'default'
        ], (array) $atts);

        $post_id = get_the_ID();
        
        if ($post_id && $this->is_featured($post_id)) {
            $classes = 'yardlii-featured-badge ' . esc_attr((string) $a['class']);
            $styles  = '';

            if ($a['style'] === 'default') {
                $styles = 'style="display:inline-block; background:#ffd700; color:#000; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:12px; text-transform:uppercase; line-height:1;"';
            }

            return sprintf(
                '<span class="%s" %s>%s</span>',
                $classes,
                $styles,
                esc_html((string) $a['text'])
            );
        }
        return '';
    }
}