<?php
declare(strict_types=1);

namespace Yardlii\Tests\Unit\Emails;

use PHPUnit\Framework\TestCase;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

/**
 * Unit tests for the Mailer class.
 * These tests MUST NOT load WordPress.
 *
 * @covers \Yardlii\Core\Features\TrustVerification\Emails\Mailer
 */
final class MailerTest extends TestCase
{
    /**
     * Test: buildRecipients correctly cleans, de-duplicates, and validates emails.
     */
    public function test_build_recipients_cleans_and_deduplicates(): void
    {
        $mailer = new Mailer();

        // 1. Test with a string of comma-separated emails
        $input_string = ' test1@example.com, test2@example.com , test1@example.com, bad-email, ';
        $expected = ['test1@example.com', 'test2@example.com'];
        $this->assertEquals($expected, $mailer->buildRecipients($input_string, []));

        // 2. Test with an array of emails
        $input_array = [' test2@example.com ', 'test1@example.com', ' ', 'test1@example.com'];
        $expected = ['test2@example.com', 'test1@example.com'];
        $this->assertEquals($expected, $mailer->buildRecipients($input_array, []));

        // 3. Test with empty input
        $this->assertEquals([], $mailer->buildRecipients('', []));
        $this->assertEquals([], $mailer->buildRecipients([], []));
    }
}