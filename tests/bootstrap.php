<?php
// Composer autoload
require dirname(__DIR__) . '/vendor/autoload.php';

// Point to WordPress test suite (provided by wp-phpunit)
if (!getenv('WP_PHPUNIT__DIR')) {
    putenv('WP_PHPUNIT__DIR=' . dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit');
}
$_tests_dir = getenv('WP_PHPUNIT__DIR');

// Boot WordPress' test suite
require $_tests_dir . '/includes/bootstrap.php';

// Load this plugin as an MU plugin during tests
tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/yardlii-core-functions.php';
});

// Do not actually send emails in tests
tests_add_filter('pre_wp_mail', function ($short_circuit, $atts = []) {
    return true; // report success and skip PHPMailer
}, 10, 2);
