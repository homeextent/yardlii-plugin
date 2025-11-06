<?php
// tests/integration/wp-bootstrap.php
declare(strict_types=1);

$tests_dir = getenv('WP_PHPUNIT__DIR') ?: dirname(__DIR__, 2) . '/vendor/wp-phpunit/wp-phpunit';

// Tell the runner where our config lives (works on Win/Linux)
define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');

require $tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2) . '/yardlii-core-functions.php';
    require __DIR__ . '/fixtures/test-ajax-endpoints.php';
});

require $tests_dir . '/includes/bootstrap.php';
