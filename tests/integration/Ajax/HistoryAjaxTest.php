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
	/**
     * Test: The endpoint must succeed and return HTML when called by an authorized user
     * with a valid nonce and request ID.
     */
    public function test_admin_with_valid_request_should_succeed(): void
    {
        // 1. Create an admin user and log in.
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        // 2. Create a fake verification request post.
        $request_id = self::factory()->post->create([
            'post_type' => 'vp_request', // CPT slug
            'post_status' => 'vp_pending',
        ]);

        // 3. Add some fake log data to the post's meta.
        $logs = [
            [
                'ts' => '2025-11-06T10:00:00Z',
                'action' => 'created',
                'by' => 123,
                'data' => ['form_id' => 'test_form']
            ]
        ];
        // The 'true' at the end means it's a single value, not an array.
        add_post_meta($request_id, '_vp_action_logs', $logs, true);

        // 4. Set the valid nonce and the request_id in the $_POST data.
        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_history');
        $_POST['request_id'] = $request_id;

        try {
            // 5. Run the handler
            $this->_handleAjax($this->ajax_action);

            $this->fail('AJAX handler did not die as expected.');
        } catch (\WPAjaxDieStopException $e) {
            // 6. We expect a JSON success response.
            $data = json_decode($e->getMessage(), true);

            $this->assertIsArray($data);
            $this->assertTrue($data['success']);

            // 7. Assert the HTML contains our log data.
            $this->assertArrayHasKey('html', $data['data']);
            $this->assertStringContainsString(
                'Request created', // This label comes from the 'created' action [cite: 387-389]
                $data['data']['html']
            );
        }
    }
}