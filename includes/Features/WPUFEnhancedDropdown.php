<?php
/**
 * Feature: WPUF Enhanced Dropdown
 * --------------------------------
 * Purpose:
 * Enhances the WP User Frontend taxonomy dropdown ("Group" field) by replacing
 * the native select element with a branded, fully responsive, hierarchical
 * accordion dropdown that matches the Yardlii design system.
 *
 * Key Features:
 * - Accordion-style expandable parent/child taxonomy view
 * - Smart resettable "Select Group" button
 * - Brand-aligned color palette (Trust Blue & Action Orange)
 * - Fully mobile responsive
 * - Configurable target pages via Admin Settings
 *
 * Location:
 * /includes/Features/WPUFEnhancedDropdown.php
 *
 * JS & CSS:
 * /assets/js/yardlii-enhanced-dropdown.js
 * /assets/css/yardlii-enhanced-dropdown.css
 *
 * @since 1.0.0
 * @package Yardlii\Core\Features
 */

namespace Yardlii\Core\Features;

class WPUFEnhancedDropdown {

    /**
     * Register the feature with WordPress hooks
     */
    public function register() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Conditionally enqueue assets only on front-end WPUF pages
     */
    public function enqueue_assets() {

        // 1. Get configured pages (comma-separated slugs or IDs)
        // Default to 'submit-a-post' to maintain backward compatibility
        $raw_targets = get_option('yardlii_wpuf_target_pages', 'submit-a-post');

        // 2. Parse into an array
        $targets = array_filter(array_map('trim', explode(',', (string)$raw_targets)));

        // 3. Safety check: If no pages configured, do not load
        if (empty($targets)) {
            return;
        }

        // 4. Check if current page matches any target
        if (!is_page($targets)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'yardlii-enhanced-dropdown',
            plugins_url('/assets/css/yardlii-enhanced-dropdown.css', YARDLII_CORE_FILE),
            [],
            filemtime(plugin_dir_path(YARDLII_CORE_FILE) . 'assets/css/yardlii-enhanced-dropdown.css')
        );

        // Enqueue JS
        wp_enqueue_script(
            'yardlii-enhanced-dropdown',
            plugins_url('/assets/js/yardlii-enhanced-dropdown.js', YARDLII_CORE_FILE),
            ['jquery'],
            filemtime(plugin_dir_path(YARDLII_CORE_FILE) . 'assets/js/yardlii-enhanced-dropdown.js'),
            true
        );
    }
}