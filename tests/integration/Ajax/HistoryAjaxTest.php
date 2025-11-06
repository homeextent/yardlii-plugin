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
     * Test: The endpoint must fail if the user lacks the 'Caps::MANAGE' capability.
     *
     * This corresponds to the current_user_can(Caps::MANAGE) check.
     */
    public function test_user_without_capability_should_fail(): void
    {
        // 1. Create a user who does NOT have the capability.
        // The 'subscriber' role is perfect for this.
        $user_id = self::factory()->user->create(['role' => 'subscriber']);

        // 2. Make this user the one "running" the test.
        wp_set_current_user($user_id);

        // 3. Set the required nonce. This user is "logged in",
        // so they can pass the nonce check.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_history');

        try {
            // Run the handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // This is the expected outcome.
            // We assert the handler sent a JSON error response.
            $response = json_decode($e->getMessage(), true);

            $this->assertIsArray($response, 'Response was not valid JSON.');
            $this->assertFalse($response['success'], 'AJAX success was not false.');
            $this->assertSame(403, $response['data']['message'][1] ?? null, 'HTTP status code was not 403.');
        }
    }
}