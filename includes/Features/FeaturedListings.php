<?php
declare(strict_types=1);

namespace Yardlii\Core\Features;

use WP_Post;
use WP_Query;

/**
 * Feature: Featured Listings Logic
 * --------------------------------
 * Unifies "Native Sticky" posts (WPUF) and "Meta Featured" posts (ACF).
 * Uses direct SQL for ID retrieval to prevent hook recursion.
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

        // 6. Enqueue Frontend Styles (New)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Load the frontend CSS for the badge.
     */
    public function enqueue_styles(): void {
        if (defined('YARDLII_CORE_URL') && defined('YARDLII_CORE_VERSION') && defined('YARDLII_CORE_FILE')) {
            wp_enqueue_style(
                'yardlii-core-frontend',
                YARDLII_CORE_URL . 'assets/css/frontend.css',
                [],
                (string) filemtime(plugin_dir_path(YARDLII_CORE_FILE) . 'assets/css/frontend.css')
            );
        }
    }

    /**
     * Helper: Check if a post is effectively featured (Sticky OR Meta).
     */
    private function is_featured(int $post_id): bool {
        if (is_sticky($post_id)) {
            return true;
        }
        $val = get_post_meta($post_id, self::META_KEY, true);
        return ($val === '1' || $val === 1 || $val === 'true' || $val === true);
    }

    /**
     * Helper: Get ALL featured IDs (Sticky + Meta) for queries.
     * @return int[]
     */
    private function get_all_featured_ids(): array {
        global $wpdb;
        $sticky_ids = get_option('sticky_posts');
        if (!is_array($sticky_ids)) $sticky_ids = [];

        $sql = $wpdb->prepare(
            "SELECT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = %s AND pm.meta_value = '1'",
            'listings', self::META_KEY
        );
        $meta_ids = $wpdb->get_col($sql);
        if (!is_array($meta_ids)) $meta_ids = [];

        $merged = array_unique(array_merge($sticky_ids, $meta_ids));
        return array_map('intval', $merged);
    }

    public function sync_sticky_status(int $post_id, WP_Post $post, bool $update): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

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

    public function add_sticky_state(array $states, WP_Post $post): array {
        if ($post->post_type !== 'listings') return $states;
        if ($this->is_featured($post->ID)) {
            if (!isset($states['sticky'])) $states['sticky'] = __('Sticky', 'yardlii-core');
        }
        return $states;
    }

    public function add_admin_filter(string $post_type): void {
        if ($post_type !== 'listings') return;
        $current = isset($_GET['yardlii_filter_featured']) ? sanitize_text_field((string) $_GET['yardlii_filter_featured']) : '';
        ?>
        <select name="yardlii_filter_featured">
            <option value=""><?php _e('All Listings', 'yardlii-core'); ?></option>
            <option value="yes" <?php selected($current, 'yes'); ?>><?php _e('Featured Only', 'yardlii-core'); ?></option>
            <option value="no" <?php selected($current, 'no'); ?>><?php _e('Standard Only', 'yardlii-core'); ?></option>
        </select>
        <?php
    }

    public function filter_admin_query(WP_Query $query): void {
        global $pagenow;
        if ($pagenow !== 'edit.php' || !$query->is_main_query()) return;
        if ($query->get('post_type') !== 'listings') return;

        if (isset($_GET['yardlii_filter_featured']) && $_GET['yardlii_filter_featured'] !== '') {
            $featured_ids = $this->get_all_featured_ids();
            $filter = sanitize_text_field((string) $_GET['yardlii_filter_featured']);

            if ($filter === 'yes') {
                if (!empty($featured_ids)) $query->set('post__in', $featured_ids);
                else $query->set('post__in', [0]);
            } elseif ($filter === 'no') {
                if (!empty($featured_ids)) $query->set('post__not_in', $featured_ids);
            }
        }
    }

    public function filter_elementor_query(WP_Query $query): void {
        $featured_ids = $this->get_all_featured_ids();
        if (!empty($featured_ids)) $query->set('post__in', $featured_ids);
        else $query->set('post__in', [0]);
        $query->set('ignore_sticky_posts', true);
    }

    /**
     * Shortcode: [yardlii_featured_badge text="Featured" class="custom-class"]
     *
     * @param array<string, mixed>|string $atts
     */
    public function render_badge_shortcode($atts): string {
        $a = shortcode_atts([
            'text'  => 'Featured',
            'class' => '',
        ], (array) $atts);

        $post_id = get_the_ID();
        
        if ($post_id && $this->is_featured($post_id)) {
            $classes = 'yardlii-featured-badge ' . esc_attr((string) $a['class']);
            
            // The optimized Y-Check Icon (currentColor allows it to take the text color)
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 580 625" class="yardlii-badge-icon" aria-hidden="true" role="img"><path fill="currentColor" transform="matrix(0.782962 0 0 0.782962 -117.444 -97.8703)" d="M220.01 318.57C222.757 318.423 225.517 318.704 228.253 318.914C269.447 322.086 318.885 316.931 351.824 347.005C362.717 356.95 371.236 369.262 379.999 380.997C389.553 393.723 399.024 406.51 408.413 419.358L476.555 510.98C492.608 532.318 508.817 553.539 525.182 574.639C533.95 585.888 543.025 597.017 551.537 608.403C555.938 614.956 558.45 621.822 556.804 629.713C554.852 639.07 552.194 647.887 549.682 656.99L538.429 698.588L520.564 764.632C511.354 798.493 505.663 831.012 474.847 852.411C454.424 866.454 423.007 870.794 398.22 869.981C390.6 869.731 390.774 863.669 392.128 857.74C393.587 851.353 395.666 844.636 397.508 838.187L407.801 802.269L434.725 708.511C439.747 691.238 444.974 673.869 449.859 656.635C452.236 648.251 453.697 637.41 449.572 629.514C444.382 619.579 435.501 609.365 428.454 600.436L400.099 564.309L275.584 402.953C265.64 390.015 255.559 377.183 245.342 364.46C235.446 352.048 222.753 336.196 214.73 322.594C216.782 320.481 217.544 320.082 220.01 318.57Z"/><path fill="currentColor" transform="matrix(0.782962 0 0 0.782962 -117.444 -97.8703)" d="M851.871 180.56C852.182 180.78 852.008 180.605 852.125 181.257C843.162 189.961 833.085 198.386 823.788 206.807C813.447 216.322 803.318 226.065 793.408 236.029C743.901 286.026 700.14 341.402 662.941 401.125C634.74 446.053 610.812 492.456 585.428 538.951C578.178 552.231 564.044 558.333 552.291 545.871C543.063 536.087 535.144 523.743 527.165 512.72L483.407 452.731C475.232 441.165 466.282 429.891 458.136 418.387C453.734 412.169 438.342 394.57 442.308 387.129C447.057 378.218 465.324 372.018 474.513 368.385C489.56 362.435 506.271 362.623 517.586 375.459C521.828 380.124 524.774 385.031 528.254 390.306C533.268 397.976 538.443 405.539 543.776 412.991C546.611 416.928 550.142 422.445 554.563 424.399C556.492 425.318 560.228 425.314 561.831 423.679C570.117 415.224 577.204 404.86 584.62 395.623C597.689 379.351 611.167 363.413 625.04 347.821C686.021 279.994 753.76 216.878 840.622 184.728C844.373 183.339 848.115 181.934 851.871 180.56Z"/></svg>';

            return sprintf(
                '<span class="%s">%s <span>%s</span></span>',
                $classes,
                $icon,
                esc_html((string) $a['text'])
            );
        }
        return '';
    }
}