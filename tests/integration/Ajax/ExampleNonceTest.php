<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration\Ajax;

use WP_Ajax_UnitTestCase;

final class ExampleNonceTest extends WP_Ajax_UnitTestCase
{
    public function test_missing_nonce_fails(): void
    {
        try {
            $this->_handleAjax('yardlii_test_echo'); // calls our fixture action
            $this->fail('AJAX did not die as expected');
        } catch (\WPAjaxDieStopException $e) {
            $data = json_decode($e->getMessage(), true);
            $this->assertIsArray($data);
            $this->assertFalse($data['success']);
            $this->assertSame('missing_or_bad_nonce', $data['data']['code'] ?? null);
        }
    }

    public function test_happy_path_with_nonce_succeeds(): void
    {
        // Simulate a logged-in user (not strictly required for this endpoint).
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $_POST['nonce']   = wp_create_nonce('yardlii_test_nonce');
        $_POST['payload'] = 'hello world';

        try {
            $this->_handleAjax('yardlii_test_echo');
            $this->fail('AJAX did not die as expected');
        } catch (\WPAjaxDieStopException $e) {
            $data = json_decode($e->getMessage(), true);
            $this->assertTrue($data['success']);
            $this->assertSame('hello world', $data['data']['echo'] ?? null);
        }
    }
}
