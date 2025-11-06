<?php
// tests/unit/bootstrap.php
// This file is loaded by phpunit.xml.dist to bootstrap the unit test environment.

// --- Mock WordPress Functions ---
// We define simple "pass-through" mocks for WP functions
// used by the classes we are unit testing.

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $key): string {
        // Return a predictable value for tests
        return ($key === 'name') ? 'Test Blog' : 'test_blog_value';
    }
}

if (!function_exists('wp_specialchars_decode')) {
    // Correctly accepts a second optional parameter [cite: 40, 90, 240]
    function wp_specialchars_decode(string $s, int $flags = ENT_QUOTES): string {
        return html_entity_decode($s, $flags);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('apply_filters')) {
    // Correctly accepts a variable number of arguments
    function apply_filters(string $hook, mixed $value, ...mixed $args): mixed {
        // We don't do anything with the filters, just return the original value
        return $value;
    }
}

if (!function_exists('is_email')) {
    function is_email(string $email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('wp_get_current_user')) {
    // Added a return type to satisfy PHPStan
    function wp_get_current_user(): \stdClass {
        $user = new \stdClass();
        $user->user_email = 'admin@example.com';
        return $user;
    }
}