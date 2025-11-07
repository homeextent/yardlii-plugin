<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yardlii\Core\Features\TrustVerification\Caps;

/**
 * Unit tests for the Caps class.
 *
 * @covers \Yardlii\Core\Features\TrustVerification\Caps
 */
class CapsTest extends TestCase
{
    /**
     * This is run before each test.
     * It calls our static helper to reset all mock data.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Reset our mock function state before every test
        CapsTest_Mock_Store::reset();
    }

    public function test_grantDefault(): void
    {
        // 1. Create a mock admin role that does *not* have the cap yet
        $mock_admin_role = new Mock_WP_Role_For_Caps();
        CapsTest_Mock_Store::$roles['administrator'] = $mock_admin_role;

        // 2. Check that it doesn't have the cap
        self::assertFalse($mock_admin_role->has_cap(Caps::MANAGE));

        // 3. Run the method
        Caps::grantDefault();

        // 4. Assert that the 'add_cap' method was effectively called
        self::assertTrue($mock_admin_role->has_cap(Caps::MANAGE));
    }

    public function test_grantDefault_doesNotAddCapIfPresent(): void
    {
        // 1. Create a mock admin role that *already* has the cap
        $mock_admin_role = new Mock_WP_Role_For_Caps([Caps::MANAGE => true]);
        CapsTest_Mock_Store::$roles['administrator'] = $mock_admin_role;

        // 2. Check that it has the cap
        self::assertTrue($mock_admin_role->has_cap(Caps::MANAGE));

        // 3. Run the method (the real 'add_cap' would not be called here)
        Caps::grantDefault();
        
        // 4. Assert it still has the cap (and no error was thrown)
        self::assertTrue($mock_admin_role->has_cap(Caps::MANAGE));
    }

    public function test_revokeDefault(): void
    {
        // 1. Create a mock admin role that *has* the cap
        $mock_admin_role = new Mock_WP_Role_For_Caps([Caps::MANAGE => true]);
        CapsTest_Mock_Store::$roles['administrator'] = $mock_admin_role;

        // 2. Check that it has the cap
        self::assertTrue($mock_admin_role->has_cap(Caps::MANAGE));

        // 3. Run the method
        Caps::revokeDefault();

        // 4. Assert that the 'remove_cap' method was effectively called
        self::assertFalse($mock_admin_role->has_cap(Caps::MANAGE));
    }

    public function test_userCanManage_forCurrentUser(): void
    {
        // Test when user CAN manage
        CapsTest_Mock_Store::$current_user_can = true;
        self::assertTrue(Caps::userCanManage());

        // Test when user CANNOT manage
        CapsTest_Mock_Store::$current_user_can = false;
        self::assertFalse(Caps::userCanManage());
    }

    public function test_userCanManage_forSpecificUser(): void
    {
        $user_id = CapsTest_Mock_Store::$mock_user->ID;

        // Test when user CAN manage
        CapsTest_Mock_Store::$specific_user_can = true;
        self::assertTrue(Caps::userCanManage($user_id));

        // Test when user CANNOT manage
        CapsTest_Mock_Store::$specific_user_can = false;
        self::assertFalse(Caps::userCanManage($user_id));
    }
    
    public function test_userCanManage_forInvalidUser(): void
    {
        // Test that it returns false for a user ID that doesn't exist
        self::assertFalse(Caps::userCanManage(99999));
    }

    public function test_restPermission(): void
    {
        // 1. Get the callable function
        $callback = Caps::restPermission();
        self::assertIsCallable($callback);

        // 2. Test its return value when current_user_can is false
        CapsTest_Mock_Store::$current_user_can = false;
        self::assertFalse($callback());

        // 3. Test its return value when current_user_can is true
        CapsTest_Mock_Store::$current_user_can = true;
        self::assertTrue($callback());
    }
}