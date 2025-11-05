<?php
use PHPUnit\Framework\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_phpunit_runs(): void
    {
        $this->assertTrue(true);
    }
}