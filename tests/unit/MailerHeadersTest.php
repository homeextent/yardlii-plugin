<?php
declare(strict_types=1);

use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

final class MailerHeadersTest extends WP_UnitTestCase
{
    public function test_headers_filter_applies(): void
    {
        $hit = false;

        add_filter('yardlii_tv_email_headers', function(array $headers, array $ctx) use (&$hit) {
            $hit = true;
            $headers[] = 'X-Test: Yes';
            return $headers;
        }, 10, 2);

        $user_id = self::factory()->user->create(['user_email' => 'test@example.com', 'display_name' => 'Testy']);
        $mailer  = new Mailer();

        $ok = $mailer->send('rcpt@example.com', 'Subj', 'Body', [
            'user'     => get_user_by('id', $user_id),
            'form_id'  => 'form-1',
            'reply_to' => 'noreply@example.com',
        ]);

        $this->assertTrue($ok, 'Mailer should return true (short-circuited by pre_wp_mail).');
        $this->assertTrue($hit, 'yardlii_tv_email_headers filter not called.');
    }
}
