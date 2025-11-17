<?php
// Minimal bootstrap for static analysis
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/'); // Point to project root
}

// Composer autoload is in the project root (dirname(__DIR__)), not the /tests directory (__DIR__)
require dirname(__DIR__) . '/vendor/autoload.php';


// --- Stubs for Action Scheduler ---
if (!function_exists('as_get_scheduled_actions')) {
    /**
     * @param array<string, mixed> $args
     * @param string $return_format 'objects'|'ids'|'count'
     * @return array|int
     */
    function as_get_scheduled_actions(array $args = [], string $return_format = 'objects') {
        if ($return_format === 'count') {
            return 0;
        }
        return [];
    }
}

if (!class_exists('ActionScheduler_Store')) {
    /**
     * Stub for \ActionScheduler_Store class
     * Provides constants for static analysis.
     */
    class ActionScheduler_Store {
        public const STATUS_PENDING = 'pending';
        public const STATUS_FAILED = 'failed';
        public const STATUS_RUNNING = 'running';
        public const STATUS_COMPLETE = 'complete';
        public const STATUS_CANCELED = 'canceled';
    }
}