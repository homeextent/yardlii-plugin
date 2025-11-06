<?php
// tests/integration/wp-tests-config.php

// DB (local values from env or defaults)
define('DB_NAME', getenv('WP_DB_NAME') ?: 'wp_phpunit_tests');
define('DB_USER', getenv('WP_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_DB_PASS') ?: '');
define('DB_HOST', getenv('WP_DB_HOST') ?: '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// Prefer WP_PHPUNIT__DIR if set, otherwise vendor path
$base = getenv('WP_PHPUNIT__DIR') ?: dirname(__DIR__, 2) . '/vendor/wp-phpunit/wp-phpunit';
$base = rtrim(str_replace('\\', '/', $base), '/');

// Try the common layouts in order
$candidates = [
    $base . '/src',        // WP develop layout
    $base . '/wordpress',  // WP “built” layout
    $base,                 // some packages put files at the base
];

$wpSrc = null;
foreach ($candidates as $c) {
    if (is_file($c . '/wp-settings.php')) {
        $wpSrc = $c . '/';
        break;
    }
}

if (!$wpSrc) {
    $tried = implode("\n- ", $candidates);
    die("wp-settings.php not found. Tried:\n- {$tried}\nResolved base: {$base}\n(Set WP_PHPUNIT__DIR to override.)");
}

define('ABSPATH', $wpSrc);
