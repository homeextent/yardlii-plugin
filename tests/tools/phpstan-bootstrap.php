<?php
// Tiny shim so PHPStan can parse files that reference these.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
