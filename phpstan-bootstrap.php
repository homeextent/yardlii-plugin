<?php
// Minimal bootstrap for static analysis
define('ABSPATH', __DIR__ . '/'); // silence some WP checks
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';