<?php
declare(strict_types=1);

// Where WordPress' test lib lives.
$tests_dir = getenv('WP_PHPUNIT__DIR') ?: dirname(__DIR__, 2) . '/vendor/wp-phpunit/wp-phpunit';

require $tests_dir . '/includes/functions.php';

// Load our plugin when the test environment boots.
tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2) . '/yardlii-core-functions.php';
    // Load test-only fixtures (our demo AJAX endpoint).
    require __DIR__ . '/fixtures/test-ajax-endpoints.php';
});

require $tests_dir . '/includes/bootstrap.php';
