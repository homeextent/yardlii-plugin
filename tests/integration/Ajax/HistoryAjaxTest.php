<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration\Ajax;

use WP_Ajax_UnitTestCase;
use Yardlii\Core\Features\TrustVerification\Caps; // We'll need this for later tests

/**
 * Tests for the 'yardlii_tv_history_load' AJAX action.
 * @covers \Yardlii\Core\Features\TrustVerification\Requests\HistoryAjax
 */
final class HistoryAjaxTest extends WP_Ajax_UnitTestCase
{
    /**
     * The AJAX action hook to test.
     * @var string
     */
    protected string $ajax_action = 'yardlii_tv_history_load';

    /**
     * Test: The endpoint must fail with a 403 error if no nonce is provided.
     *
     * This corresponds to the check_ajax_referer('yardlii_tv_history', ...)
     * in the handle() method.
     */
    public function test_missing_nonce_should_fail(): void
    {
        try {
            // This runs the AJAX action handler
            $this->_handleAjax($this->ajax_action);

            // If the line above doesn't 'die', the test failed.
            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // This is the expected outcome.
            // A 403 'Forbidden' response is the default for check_ajax_referer() failure.
            $this->assertEquals('403', $e->getMessage());
        }
    }
	
    /**
     * Test: The endpoint must fail if the user doesn't have the 'manage_verifications' cap.
     */
    public function test_user_without_cap_should_fail(): void
    {
        // 1. Create a user who does NOT have the correct capability.
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        // 2. Set a valid nonce for this user.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_history');

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
                'Permission denied',
                $data['data']['message'] ?? ''
            );
        }
    }
}