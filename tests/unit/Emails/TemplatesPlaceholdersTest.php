<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit\Emails;

use PHPUnit\Framework\TestCase;
use Yardlii\Core\Features\TrustVerification\Emails\Templates;

/**
 * Unit tests for the Templates class.
 * These tests MUST NOT load WordPress.
 *
 * @covers \Yardlii\Core\Features\TrustVerification\Emails\Templates
 */
final class TemplatesPlaceholdersTest extends TestCase
{
    /**
     * Test: It correctly replaces legacy {token} placeholders.
     */
    public function test_merge_placeholders_replaces_legacy_tokens(): void
    {
        $html = 'Hello {display_name}, your request {request_id} is complete.';
        
        $context = [
            '{display_name}' => 'Test User',
            '{request_id}' => '123',
            '{unused_token}' => 'foo',
        ];

        $result = Templates::mergePlaceholders($html, $context);

        $this->assertEquals(
            'Hello Test User, your request 123 is complete.',
            $result
        );
    }
}