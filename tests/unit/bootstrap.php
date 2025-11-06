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
    function wp_specialchars_decode(string $s): string {
        return html_entity_decode($s, ENT_QUOTES);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        // A very simple mock.
        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('apply_filters')) {
    // This mock allows the value to be filtered.
    // In our tests, we won't add filters, so it just returns the value.
    function apply_filters(string $hook, $value) {
        return $value;
    }
}

if (!function_exists('is_email')) {
    function is_email(string $email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        // Return a mock WP_User object for tests that need it.
        $user = new \stdClass();
        $user->user_email = 'admin@example.com';
        return $user;
    }
}
