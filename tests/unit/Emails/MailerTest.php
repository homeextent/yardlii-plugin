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
    /**
     * Test: buildHeaders correctly assembles From and Reply-To headers.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_build_headers_assembles_correctly_without_context(): void
    {
        $mailer = new Mailer();
        $headers = $mailer->buildHeaders([]); // Pass empty context

        // 1. Check for Content-Type
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $headers);

        // 2. Check for From (should use get_bloginfo fallbacks)
        // get_bloginfo('name') returns 'Test Blog' from our bootstrap
        $this->assertContains('From: Test Blog <test_blog_value>', $headers);

        // 3. Check for Reply-To (should fall back to From email)
        $this->assertContains('Reply-To: test_blog_value', $headers);
    }

    /**
     * Test: buildHeaders correctly uses 'reply_to' from context.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_build_headers_uses_context_for_reply_to(): void
    {
        $mailer = new Mailer();
        $context = [
            'reply_to' => 'custom-reply@example.com',
        ];
        $headers = $mailer->buildHeaders($context);

        // Check Reply-To is the custom one
        $this->assertContains('Reply-To: custom-reply@example.com', $headers);
    }
}