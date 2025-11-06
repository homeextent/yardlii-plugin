<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration\Ajax;

use WP_Ajax_UnitTestCase;
use Yardlii\Core\Features\TrustVerification\Caps;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;

/**
 * Tests for the 'yardlii_tv_send_all_tests' AJAX action.
 * @covers \Yardlii\Core\Features\TrustVerification\Emails\SendAllTestsAjax
 */
final class SendAllTestsAjaxTest extends WP_Ajax_UnitTestCase
{
    /**
     * The AJAX action hook to test.
     * @var string
     */
    protected string $ajax_action = 'yardlii_tv_send_all_tests';

    /**
     * Set up the test environment.
     * We need to hook into 'wp_mail' to spy on email sends.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Reset the mailer's 'sent' log before each test
        reset_phpmailer_instance();
    }

    /**
     * Helper: Get the PHPMailer instance to check sent emails.
     */
    private function get_mailer()
    {
        global $phpmailer;
        if (! isset($phpmailer)) {
            $phpmailer = \MockPHPMailer::get_mock();
        }
        return $phpmailer;
    }

    /**
     * Test: The endpoint must fail if no nonce is provided.
     *
     * This corresponds to the check_ajax_referer('yardlii_tv_send_test', ...)
     * in the handle() method.
     */
    public function test_missing_nonce_should_fail(): void
    S{
        try {
            // Run the AJAX action handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // Expected outcome.
            // A 403 'Forbidden' response is the default for check_ajax_referer() failure.
            $this->assertEquals('403', $e->getMessage());
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
        // It uses the 'yardlii_tv_send_test' nonce.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_send_test');

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
                'Insufficient permissions', // This is the error message from SendAllTestsAjax.php
                $data['data']['message'] ?? ''
            );
        }
}