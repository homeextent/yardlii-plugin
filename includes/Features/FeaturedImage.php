<?php
namespace Yardlii\Core\Features;

class FeaturedImage
{
    public function register(): void
    {
        // Hook into WPUF form submissions
        add_action('wpuf_add_post_after_insert', [$this, 'maybe_set_featured_image'], 10, 4);

        // Display notices in admin (if enabled)
        add_action('admin_notices', [$this, 'display_admin_notice']);

        // ✅ NEW: AJAX test endpoint
        add_action('wp_ajax_yardlii_test_featured_image', [$this, 'ajax_test_featured_image']);
         
        // ✅ NEW: AJAX reset endpoint
    add_action('wp_ajax_yardlii_reset_featured_image', [$this, 'ajax_reset_featured_image']);
    }

    /**
     * Automatically set the featured image after a WPUF form submission
     */
    public function maybe_set_featured_image($post_id, $form_id, $form_settings, $form_vars)
    {
        // --- 1. MODIFICATION: Get an array of forms, not a single one ---
        $target_forms = (array) get_option('yardlii_listing_form_id', []);
        // --- End Modification ---
        
        $acf_field   = get_option('yardlii_featured_image_field');
        $debug_mode  = (bool) get_option('yardlii_featured_image_debug', false);

        // --- 2. MODIFICATION: Check if form ID is in the array ---
        // Only run for the configured form(s)
        if ( empty($target_forms) || ! in_array( (int) $form_id, $target_forms, true ) || empty($acf_field) ) {
            if ($debug_mode) {
                $this->set_notice('⚠️ Featured image automation skipped — no matching form or gallery field.', 'warning');
            }
            return;
        }

        $images = get_field($acf_field, $post_id);
        if (empty($images) || !is_array($images)) {
            if ($debug_mode) {
                $this->set_notice('⚠️ No gallery images found for post ID ' . (int) $post_id . '.', 'warning');
            }
            return;
        }

        // Get the first image ID
        $first_image_id = $images[0]['ID'] ?? $images[0] ?? null;
        if (!$first_image_id) {
            if ($debug_mode) {
                $this->set_notice('⚠️ Unable to extract image ID from gallery for post ID ' . (int) $post_id . '.', 'warning');
            }
            return;
        }

        set_post_thumbnail($post_id, $first_image_id);

        if ($debug_mode) {
            $post = get_post($post_id);
            $title = $post ? $post->post_title : 'Untitled';
            $this->set_notice(
                sprintf('🖼️ Featured image set for post "%s" (ID %d)', esc_html($title), (int) $post_id),
                'success'
            );
        }
    }

    /**
     * ✅ NEW: AJAX handler for "Run Test" button in admin
     */
    public function ajax_test_featured_image(): void
    {
        check_ajax_referer('yardlii_test_featured_image');

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json(['success' => false, 'message' => 'Invalid post ID.']);
        }

        $acf_field  = get_option('yardlii_featured_image_field');
        $debug_mode = (bool) get_option('yardlii_featured_image_debug', false);

        if (empty($acf_field)) {
            wp_send_json(['success' => false, 'message' => 'ACF gallery field not configured.']);
        }

        $images = get_field($acf_field, $post_id);
        if (empty($images)) {
            wp_send_json(['success' => false, 'message' => 'No images found in the configured gallery field.']);
        }

        $first_image_id = $images[0]['ID'] ?? $images[0] ?? null;
        if (!$first_image_id) {
            wp_send_json(['success' => false, 'message' => 'Could not determine image ID.']);
        }

        set_post_thumbnail($post_id, $first_image_id);

        $post = get_post($post_id);
        $title = $post ? $post->post_title : 'Untitled';

        if ($debug_mode) {
            $this->set_notice(sprintf('🧪 Test automation: Featured image set for "%s" (ID %d)', $title, $post_id));
        }

        wp_send_json([
            'success' => true,
            'message' => '✅ Featured image set successfully for "' . esc_html($title) . '".'
        ]);
    }

    /**
     * Store admin notice transient (short-lived)
     */
    private function set_notice(string $message, string $type = 'success'): void
    {
        $debug_mode = (bool) get_option('yardlii_featured_image_debug', false);
        if (!$debug_mode) return;

        set_transient('yardlii_featured_image_notice', [
            'message' => $message,
            'type'    => $type,
        ], 30);
    }

    /**
     * Display admin notice if debug mode is on
     */
    public function display_admin_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $debug_mode = (bool) get_option('yardlii_featured_image_debug', false);
        if (!$debug_mode) return;

        $notice = get_transient('yardlii_featured_image_notice');
        if ($notice) {
            delete_transient('yardlii_featured_image_notice');

            $type_class = $notice['type'] === 'warning' ? 'notice-warning' : 'notice-success';
            $icon = $notice['type'] === 'warning' ? '⚠️' : '🖼️';

            ?>
            <div class="notice <?php echo esc_attr($type_class); ?> is-dismissible yardlii-admin-notice" style="border-left:4px solid #0073aa;padding:12px 15px;">
                <p style="margin:0;">
                    <strong><?php echo esc_html($icon . ' YARDLII Notice:'); ?></strong>
                    <?php echo esc_html($notice['message']); ?>
                </p>
            </div>
            <?php
        }
      
    }
        /**
     * ✅ NEW: AJAX handler for resetting featured image
     */
    public function ajax_reset_featured_image(): void
    {
        check_ajax_referer('yardlii_test_featured_image');

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json(['success' => false, 'message' => 'Invalid post ID.']);
        }

        // Attempt to remove the current featured image
        if (delete_post_thumbnail($post_id)) {
            $post  = get_post($post_id);
            $title = $post ? $post->post_title : 'Untitled';
            $msg   = '🧹 Featured image reset for "' . esc_html($title) . '" (ID ' . $post_id . ').';

            $debug_mode = (bool) get_option('yardlii_featured_image_debug', false);
            if ($debug_mode) {
                $this->set_notice($msg, 'warning');
            }

            wp_send_json(['success' => true, 'message' => '✅ ' . $msg]);
        } else {
            wp_send_json(['success' => false, 'message' => 'No featured image was set for this post.']);
        }
    }

}
