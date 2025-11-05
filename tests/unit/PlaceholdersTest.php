<?php
declare(strict_types=1);

use Yardlii\Core\Features\TrustVerification\Emails\Templates;

final class PlaceholdersTest extends WP_UnitTestCase
{
    public function test_legacy_and_double_curly_merge(): void
    {
        $user_id = self::factory()->user->create([
            'display_name' => 'Ada Lovelace',
            'user_email'   => 'ada@example.com',
            'user_login'   => 'ada',
        ]);

        $ctx = Templates::buildContext($user_id, 'form-123', 0);

        $in  = 'Hi {display_name}, welcome to {{site.name}} — {{user.email}}';
        $out = Templates::mergePlaceholders($in, $ctx);

        $this->assertStringContainsString('Ada Lovelace', $out);
        $this->assertStringContainsString(get_bloginfo('name'), $out);
        $this->assertStringContainsString('ada@example.com', $out);
    }
}
