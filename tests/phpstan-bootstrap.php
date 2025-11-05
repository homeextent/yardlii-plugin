<?php
// tests/phpstan-bootstrap.php

// PHPStan runs without WP core, so we define only what's needed to satisfy types.

if (!class_exists('WP_List_Table')) {
    /**
     * Minimal stub so PHPStan knows the parent exists.
     * Methods/properties are intentionally omitted.
     */
    abstract class WP_List_Table {}
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return (string) $text; }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return (string) $text; }
}
