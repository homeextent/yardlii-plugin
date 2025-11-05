<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LegacyTokensTest extends TestCase
{
    public function test_legacy_tokens_replaced_with_strtr_style_map(): void
    {
        $map = ['{display_name}' => 'Jane', '{site_title}' => 'Yardlii'];
        $in  = 'Hi {display_name}, welcome to {site_title}.';
        $out = strtr($in, $map);

        $this->assertSame('Hi Jane, welcome to Yardlii.', $out);
    }
}
