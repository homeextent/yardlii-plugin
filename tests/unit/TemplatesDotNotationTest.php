<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yardlii\Core\Features\TrustVerification\Emails\Templates;

final class TemplatesDotNotationTest extends TestCase
{
    public function test_legacy_curly_tokens_still_work(): void
    {
        $ctx = Templates::buildContext(null, 'form_1', 42);
        $html = 'Hi {display_name}, your request #{request_id} was received.';
        $out  = Templates::mergePlaceholders($html, $ctx);

        $this->assertStringContainsString('Sample User', $out);
        $this->assertStringContainsString('#42', $out);
    }

    public function test_double_curly_nested_path_from_array(): void
    {
        $ctx = [
            'user' => ['profile' => ['email' => 'u@example.com', 'name' => 'Uma']],
            'a'    => ['b' => ['c' => 'see']],
        ];
        $html = 'User: {{user.profile.name}} / Email: {{user.profile.email}} / Deep: {{a.b.c}}';
        $out  = Templates::mergePlaceholders($html, $ctx);

        $this->assertSame('User: Uma / Email: u@example.com / Deep: see', $out);
    }

    public function test_unknown_tokens_are_left_as_is(): void
    {
        $ctx  = ['known' => 'yes'];
        $html = 'Hello {{missing.token}} at Yardlii';
        $out  = Templates::mergePlaceholders($html, $ctx);

        $this->assertStringContainsString('{{missing.token}}', $out);
    }

    public function test_non_stringable_values_do_not_replace(): void
    {
        $ctx  = ['obj' => new \stdClass(), 'arr' => ['x' => 1]];
        $html = 'Obj={{obj}} Arr={{arr}}';
        $out  = Templates::mergePlaceholders($html, $ctx);

        $this->assertSame('Obj={{obj}} Arr={{arr}}', $out);
    }
}
