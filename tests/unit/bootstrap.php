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
    // Correctly accepts a second optional parameter
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
    function apply_filters(string $hook, mixed $value, mixed...$args): mixed {
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

// --- Mocks for CapsTest ---

/**
 * A simple static "store" to hold the data for our CapsTest mocks.
 * This lets our PHPUnit test control the behavior of the global mock functions.
 */
class CapsTest_Mock_Store
{
    /** @var array<string, Mock_WP_Role_For_Caps> */
    public static array $roles = [];
    
    public static bool $current_user_can = false;
    
    public static bool $specific_user_can = false;
    
    public static ?object $mock_user = null;

    /**
     * Resets the store to a clean state before each test.
     */
    public static function reset(): void
    {
        self::$roles = [];
        self::$current_user_can = false;
        self::$specific_user_can = false;
        self::$mock_user = (object) ['ID' => 123, 'user_login' => 'test_user'];
    }
}

/**
 * A mock WP_Role class for our tests.
 * It just needs the has_cap, add_cap, and remove_cap methods.
 */
if (!class_exists('Mock_WP_Role_For_Caps')) {
    class Mock_WP_Role_For_Caps
    {
        /** @var array<string, bool> */
        public array $caps = []; // Public so we can check it in our test
        
        /** @var array<string, bool> */
        public array $capabilities = []; // NEW: Added to fix PHPStan error

        /** @param array<string, bool> $initial_caps */
        public function __construct(array $initial_caps = []) {
            $this->caps = $initial_caps;
            $this->capabilities = $initial_caps; // NEW: Also populate this
        }
        public function has_cap(string $cap): bool {
            return isset($this->caps[$cap]);
        }
        public function add_cap(string $cap): void {
            $this->caps[$cap] = true;
        }
        public function remove_cap(string $cap): void {
            unset($this->caps[$cap]);
        }
    }
}

// --- Mock the global WordPress functions ---

if (!function_exists('get_role')) {
    function get_role(string $role_slug): ?Mock_WP_Role_For_Caps
    {
        // Return the mock role (or null) from our static store
        return CapsTest_Mock_Store::$roles[$role_slug] ?? null;
    }
}

if (!function_exists('current_user_can')) {
    // NEW: Added ...$args to tolerate bad calls from other files
    function current_user_can(string $capability, mixed ...$args): bool
    {
        // We only care about the MANAGE cap for these tests
        if ($capability === \Yardlii\Core\Features\TrustVerification\Caps::MANAGE) {
            return CapsTest_Mock_Store::$current_user_can;
        }
        return false;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by(string $field, mixed $value): ?object
    {
        // Only return a user if the test is asking for our specific mock user
        if (CapsTest_Mock_Store::$mock_user && $field === 'id' && $value === CapsTest_Mock_Store::$mock_user->ID) {
            return CapsTest_Mock_Store::$mock_user;
        }
        return null;
    }
}

if (!function_exists('user_can')) {
    // NEW: Changed $user from 'object' to 'mixed' to tolerate bad calls
    function user_can(mixed $user, string $capability): bool
    {
        // Check if it's our mock user and the correct capability
        if (is_object($user) && $user === CapsTest_Mock_Store::$mock_user && $capability === \Yardlii\Core\Features\TrustVerification\Caps::MANAGE) {
            return CapsTest_Mock_Store::$specific_user_can;
        }
        // Fail safe for bad calls (like an int)
        return false;
    }
}