<?php
// Composer autoload for dev deps + PSR-4 autoload for plugin classes.
require __DIR__ . '/../vendor/autoload.php';

// Keep tests deterministic.
date_default_timezone_set('UTC');

// Define a couple of common WP constants so classes that reference them don't explode.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
