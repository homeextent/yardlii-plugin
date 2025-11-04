<?php
declare(strict_types=1);

namespace Yardlii\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PendingIntegrationTest extends TestCase
{
    public function testWillBeReplacedWithRealIntegrationTests(): void
    {
        $this->markTestSkipped('Integration tests will be added once WP test env is wired');
    }
}
