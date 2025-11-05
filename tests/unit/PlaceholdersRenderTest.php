<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yardlii\Core\Features\TrustVerification\Emails\Templates;

final class PlaceholdersRenderTest extends TestCase
{
    public function test_double_curly_tokens_render_from_nested_context(): void
    {
        $ctx = [
            'user' => ['display_name' => 'Jane Example', 'email' => 'jane@example.com'],
            'site' => ['name' => 'Yardlii'],
            'form_id' => 'form-123',
        ];

        $in  = 'Hi {{user.display_name}}, welcome to {{site.name}} ({{form_id}}).';
        $out = Templates::mergePlaceholders($in, $ctx);

        $this->assertStringContainsString('Jane Example', $out);
        $this->assertStringContainsString('Yardlii', $out);
        $this->assertStringContainsString('form-123', $out);
    }

    public function test_unknown_tokens_are_left_as_is(): void
    {
        $ctx = ['site' => ['name' => 'Yardlii']];
        $in  = 'Hello {{missing.token}} at {{site.name}}';
        $out = Templates::mergePlaceholders($in, $ctx);

        $this->assertStringContainsString('{{missing.token}}', $out);
        $this->assertStringContainsString('Yardlii', $out);
    }

    public function test_html_body_preserves_markup(): void
    {
        $ctx = ['user' => ['display_name' => 'Jane']];
        $in  = '<p>Hi <strong>{{user.display_name}}</strong></p>';
        $out = Templates::mergePlaceholders($in, $ctx);

        $this->assertStringContainsString('<strong>Jane</strong>', $out);
    }
}
