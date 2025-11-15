<?php
declare(strict_types=1);

namespace Yardlii\Core\Features;

use WP_Post;
use WP_Query;

/**
 * Feature: Featured Listings Logic
 * --------------------------------
 * Synchronizes the 'is_featured_item' meta key with native Sticky posts.
 * Adds Admin Filters, Elementor Queries, and a Badge Shortcode.
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

        // 5. Force "Sticky" label on CPT Admin List (Fixes missing visual feedback)
        add_filter('display_post_states', [$this, 'add_sticky_state'], 10, 2);
    }

    /**
     * Sync Logic: If meta is '1', make post Sticky. If '0', unstick.
     */
    public function sync_sticky_status(int $post_id, WP_Post $post, bool $update): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 1. Try to get value from $_POST (most reliable during manual Update)
        if (isset($_POST['acf'])) {
             // If ACF is present, we can't easily guess the key hash, 
             // so we fall through to get_post_meta unless we want to parse $_POST recursively.
             // Ideally, we rely on priority 20 (after ACF saves).
        }

        // 2. Check the direct meta key in $_POST (WPUF usually sends this directly)
        if (isset($_POST[self::META_KEY])) {
            $val = $_POST[self::META_KEY];
        } else {
            // 3. Fallback: Read from DB (ACF should have saved by priority 20)
            $val = get_post_meta($post_id, self::META_KEY, true);
        }

        // Normalize: '1', 1, 'true', true
        $is_featured = ($val === '1' || $val === 1 || $val === true || $val === 'true');

        if ($is_featured) {
            stick_post($post_id);
        } else {
            unstick_post($post_id);
        }
    }

    /**
     * Visual Fix: Add "Sticky" label to the Admin List table for CPTs.
     * WordPress usually only does this for standard 'post' types.
     *
     * @param array<string, string> $states
     */
    public function add_sticky_state(array $states, WP_Post $post): array {
        // Only affect our CPT
        if ($post->post_type !== 'listings') {
            return $states;
        }

        // Check the native source of truth
        if (is_sticky($post->ID)) {
            // Add the label if not already present
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
        global $pagenow;
        if ($pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'listings') {
            return;
        }

        if (isset($_GET['yardlii_filter_featured']) && $_GET['yardlii_filter_featured'] !== '') {
            $sticky_posts = get_option('sticky_posts');
            $filter = sanitize_text_field((string) $_GET['yardlii_filter_featured']);
            
            $sticky_ids = is_array($sticky_posts) ? $sticky_posts : [];

            if ($filter === 'yes') {
                if (!empty($sticky_ids)) {
                    $query->set('post__in', $sticky_ids);
                } else {
                    $query->set('post__in', [0]); 
                }
            } elseif ($filter === 'no') {
                if (!empty($sticky_ids)) {
                    $query->set('post__not_in', $sticky_ids);
                }
            }
        }
    }

    /**
     * Elementor Query Filter
     * ID: featured_listings
     */
    public function filter_elementor_query(WP_Query $query): void {
        $sticky_posts = get_option('sticky_posts');
        $sticky_ids = is_array($sticky_posts) ? $sticky_posts : [];

        if (!empty($sticky_ids)) {
            $query->set('post__in', $sticky_ids);
        } else {
            $query->set('post__in', [0]); 
        }
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
        
        if ($post_id && is_sticky($post_id)) {
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