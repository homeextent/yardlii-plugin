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
}