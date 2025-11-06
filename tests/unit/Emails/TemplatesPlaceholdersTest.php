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
     * Test: It correctly leaves unknown/missing tokens in place.
     */
    public function test_merge_placeholders_leaves_unknown_tokens(): void
    {
        $html = 'Hi {{user.name}}, your token is {{unknown.token}} and {legacy_unknown}.';
        
        $context = [
            'user' => [
                'name' => 'Test User',
            ],
            '{legacy_known}' => 'foo', // This one won't be used
        ];

        $result = Templates::mergePlaceholders($html, $context);

        // We expect {{user.name}} to be replaced, but the others to be left alone.
        $this->assertEquals(
            'Hi Test User, your token is {{unknown.token}} and {legacy_unknown}.',
            $result
        );
    }
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
    /**
     * Test: It correctly replaces modern {{dot.notation}} placeholders.
     * This also tests the recursive array flattening.
     */
    public function test_merge_placeholders_replaces_modern_dot_notation_tokens(): void
    {
        $html = 'Hi {{user.name}}, site is {{site.url}}. Your form is {{form.id}}.';
        
        // Context is a nested array, not flat.
        $context = [
            'user' => [
                'name' => 'Modern User',
                'id' => 5,
            ],
            'site' => [
                'url' => 'https://example.com',
            ],
            'form' => [
                'id' => 'f_123'
            ]
        ];

        $result = Templates::mergePlaceholders($html, $context);

        $this->assertEquals(
            'Hi Modern User, site is https://example.com. Your form is f_123.',
            $result
        );
    }
}