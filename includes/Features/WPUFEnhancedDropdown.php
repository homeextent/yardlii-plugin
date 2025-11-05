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
 * - Clean integration into WPUF form #339 ("Submit a Post")
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

        // Load only on the "Submit a Post" page (WPUF form #339)
        if (!is_page('submit-a-post')) {
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
