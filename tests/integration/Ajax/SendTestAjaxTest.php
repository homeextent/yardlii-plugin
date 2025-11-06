<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration\Ajax;

use WP_Ajax_UnitTestCase;
use Yardlii\Core\Features\TrustVerification\Caps;

/**
 * Tests for the 'yardlii_tv_send_test_email' AJAX action.
 * @covers \Yardlii\Core\Features\TrustVerification\Emails\SendTestAjax
 */
final class SendTestAjaxTest extends WP_Ajax_UnitTestCase
{
    /**
     * The AJAX action hook to test.
     * @var string
     */
    protected string $ajax_action = 'yardlii_tv_send_test_email';
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
    {
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
        // The nonce name must match the one used in the SendTestAjax.php file.
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
                'Insufficient permissions', // This is the error message from SendTestAjax.php 
                $data['data']['message'] ?? ''
            );
        }
    }
    /**
     * Test: The endpoint must succeed and send an email for an authorized user.
     */
    public function test_admin_with_valid_data_should_succeed_and_send_email(): void
    {
        // 1. Create an admin user and log in.
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        $admin = get_userdata($admin_id);
        wp_set_current_user($admin_id);

        // 2. Set the expected $_POST data for the handler.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_send_test');
        $_POST['form_id'] = 'test_form_123';
        $_POST['email_type'] = 'approve';
        $_POST['to'] = 'test@example.com';
        $_POST['subject'] = 'Test Subject'; // Subject override
        $_POST['body'] = '<p>Test Body</p>'; // Body override

        try {
            // 3. Run the handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // 4. We expect a JSON success response.
            $data = json_decode($e->getMessage(), true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);
            $this.->assertStringContainsString('Test email sent to', $data['data']['message']);

            // 5. Now, check that the email was actually sent.
            $mailer = $this->get_mailer();
            $this->assertCount(1, $mailer->mock_sent); // One email was sent
            $sent_email = $mailer->mock_sent[0];

            $this->assertEquals('test@example.com', $sent_email['to'][0][0]); // Correct recipient
            $this->assertEquals('Test Subject', $sent_email['subject']); // Correct subject
            $this->assertStringContainsString('<p>Test Body</p>', $sent_email['body']); // Correct body

            // Check that the Reply-To header was correctly set to the admin's email
            $this->assertStringContainsString($admin->user_email, $sent_email['header']);
            $this->assertStringContainsString('Reply-To:', $sent_email['header']);
        }
    }
}