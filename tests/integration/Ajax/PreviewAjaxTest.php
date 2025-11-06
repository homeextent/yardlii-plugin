<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration\Ajax;

use WP_Ajax_UnitTestCase;
use Yardlii\Core\Features\TrustVerification\Caps;

/**
 * Tests for the 'yardlii_tv_preview_email' AJAX action.
 * @covers \Yardlii\Core\Features\TrustVerification\Emails\PreviewAjax
 */
final class PreviewAjaxTest extends WP_Ajax_UnitTestCase
{
    /**
     * The AJAX action hook to test.
     * @var string
     */
    protected string $ajax_action = 'yardlii_tv_preview_email';

    /**
     * Test: The endpoint must fail if no nonce is provided.
     *
     * This corresponds to the check_ajax_referer('yardlii_tv_preview', ...)
     * in the handle() method.
     */
    public function test_missing_nonce_should_fail(): void
    {
        try {
            // Run the AJAX action handler
            $this->_handleAjax($this->ajax_action);

            $this.->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // Expected outcome.
            // A 403 'Forbidden' response is the default for check_ajax_referer() failure.
            $this.->assertEquals('403', $e->getMessage());
        }
    }
    /**
     * Test: The endpoint must fail if the user doesn't have the 'manage_verifications' cap.
     */
    public function test_user_without_cap_should_fail(): void
    {
        // 1. Create a 'subscriber' user who lacks the required capability.
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        // 2. Set a valid nonce for this user.
        // The nonce name must match the one used in the PreviewAjax.php file.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_preview');

        try {
            // 3. Run the handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // 4. We expect a JSON error response.
            $data = json_decode($e->getMessage(), true);

            $this->assertIsArray($data);
            $this->assertFalse($data['success']);
            $this->assertStringContainsString(
                'Insufficient permissions', // This is the error message from PreviewAjax.php
                $data['data']['message'] ?? ''
            );
        }
    }
    /**
     * Test: The endpoint must succeed and return merged HTML for an authorized user.
     */
    publicF function test_admin_with_valid_data_should_succeed(): void
    {
        // 1. Create an admin user and log in.
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        // 2. Set the expected $_POST data for the handler.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_preview');
        $_POST['form_id'] = 'test_form_456';
        $_POST['email_type'] = 'reject';
        // We will send unsaved overrides from the UI
        $_POST['subject'] = 'Preview Subject {{site.name}}';
        $_POST['body'] = '<p>Hello {{user.display_name}}, this is a test.</p>';

        try {
            // 3. Run the handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // 4. We expect a JSON success response.
            $data = json_decode($e->getMessage(), true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('html', $data['data']);

            // 5. Assert the HTML contains the merged subject and body.
            // The PreviewAjax handler prepends the subject to the HTML.
            $this->assertStringContainsString(
                'Preview Subject My Test Blog', // '{{site.name}}' becomes 'My Test Blog'
                $data['data']['html']
            );
            $this->assertStringContainsString(
                '<p>Hello administrator, this is a test.</p>', // '{{user.display_name}}' becomes 'administrator'
                $data['data']['html']
            );
        }
    }
}