<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AjaxSendAllTestsNonceTest extends TestCase
{
    public function test_pending_until_wp_suite_is_configured(): void
    {
        $this->markTestSkipped('WP integration suite not bootstrapped yet. Will assert nonce + caps on yardlii_tv_send_all_tests.');
    }
}
