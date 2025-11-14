<?php
/**
 * Feature: WPUF Frontend Enhancements
 * -----------------------------------
 * Purpose:
 * Centralized controller for visual and functional improvements to WPUF forms.
 * * Managed Features:
 * 1. Enhanced Dropdown: Replaces native select with branded accordion.
 * 2. Card-Style Layout: Groups form fields into visual "cards" based on section breaks.
 *
 * Configuration:
 * Controlled via Settings -> YARDLII Core -> General -> WPUF Customisations.
 * Target pages are defined in 'yardlii_wpuf_target_pages'.
 *
 * @since 3.9.0 (Renamed from WPUFEnhancedDropdown)
 * @package Yardlii\Core\Features
 */

namespace Yardlii\Core\Features;

class WPUFFrontendEnhancements {

    /**
     * Register the feature with WordPress hooks
     */
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Conditionally enqueue assets based on active features and target pages
     */
    public function enqueue_assets(): void {

        // PHPStan Guard: Ensure core constant is defined before using it in plugins_url
        if (!defined('YARDLII_CORE_FILE')) {
            return;
        }

        // 1. Check Target Pages
        $raw_targets = get_option('yardlii_wpuf_target_pages', 'submit-a-post');
        $targets = array_filter(array_map('trim', explode(',', (string)$raw_targets)));

        if (empty($targets) || !is_page($targets)) {
            return;
        }

        // 2. Feature: Enhanced Dropdown
        if (get_option('yardlii_enable_wpuf_dropdown', true)) {
            wp_enqueue_style(
                'yardlii-enhanced-dropdown',
                plugins_url('/assets/css/yardlii-enhanced-dropdown.css', YARDLII_CORE_FILE),
                [],
                filemtime(plugin_dir_path(YARDLII_CORE_FILE) . 'assets/css/yardlii-enhanced-dropdown.css')
            );

            wp_enqueue_script(
                'yardlii-enhanced-dropdown',
                plugins_url('/assets/js/yardlii-enhanced-dropdown.js', YARDLII_CORE_FILE),
                ['jquery'],
                filemtime(plugin_dir_path(YARDLII_CORE_FILE) . 'assets/js/yardlii-enhanced-dropdown.js'),
                true
            );
        }

        // 3. Feature: Card-Style Layout
        if (get_option('yardlii_wpuf_card_layout', false)) {
            wp_enqueue_style(
                'yardlii-wpuf-cards',
                plugins_url('/assets/css/yardlii-wpuf-cards.css', YARDLII_CORE_FILE),
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'yardlii-wpuf-cards',
                plugins_url('/assets/js/yardlii-wpuf-cards.js', YARDLII_CORE_FILE),
                ['jquery'],
                '1.0.0',
                true
            );
        }
    }
}