<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase
{
    public function testAutoloaderFindsAClass(): void
    {
        // Pick a class that definitely exists in your plugin.
        $this->assertTrue(
            class_exists(\Yardlii\Core\Core::class),
            'Composer autoloader did not find Yardlii\\Core\\Core'
        );
    }
}
