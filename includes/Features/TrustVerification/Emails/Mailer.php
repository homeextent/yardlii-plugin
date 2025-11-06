<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

final class Mailer
{
    /**
     * Send an HTML email with centralized From/Reply-To and filters.
     * $to may be string or array. Subject/body placeholders are rendered here.
     */
    public function send($to, string $subject, string $html, array $context = []): bool
    {
        // Allow sites to enrich/modify the context
        $context = apply_filters('yardlii_tv_placeholder_context', $context);

        // Recipients
        $recipients = $this->buildRecipients($to, $context);
        if (empty($recipients)) {
            return false;
        }

        // ✅ Render placeholders in both subject AND body (supports {{…}} and legacy {…})
        $subject = $this->renderPlaceholders($subject, $context);
        $body    = $this->renderPlaceholders($html,    $context);

        // Headers
        $headers = $this->buildHeaders($context);
        $headers = (array) apply_filters('yardlii_tv_email_headers', $headers, $context);

        // Final recipients filter
        $recipients = (array) apply_filters('yardlii_tv_email_recipients', $recipients, $context);

        // Send individually
        $ok = true;
        foreach ($recipients as $addr) {
            if (!wp_mail($addr, $subject, $body, $headers)) {
                $ok = false;
            }
        }
        return $ok;
    }

    private function getFromPair(array $context): array
    {
        $site  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $email = get_bloginfo('admin_email');
        $pair  = [
            'name'  => (string) apply_filters('yardlii_tv_from_name', $site, $context),
            'email' => (string) apply_filters('yardlii_tv_from_email', $email, $context),
        ];
        return (array) apply_filters('yardlii_tv_from', $pair, $context);
    }

    public function buildHeaders(array $context): array
    {
        $h = ['Content-Type: text/html; charset=UTF-8'];

        $from = $this->getFromPair($context);
        $fromName  = isset($from['name']) ? trim((string) $from['name']) : '';
        $fromEmail = isset($from['email']) ? sanitize_email((string) $from['email']) : '';
        if ($fromEmail) {
            $h[] = sprintf('From: %s <%s>', $fromName !== '' ? $fromName : get_bloginfo('name'), $fromEmail);
        }

        $replyTo = '';
        if (!empty($context['reply_to']) && is_email((string) $context['reply_to'])) {
            $replyTo = (string) $context['reply_to'];
        } elseif ($fromEmail) {
            $replyTo = $fromEmail;
        }
        if ($replyTo) {
            $h[] = 'Reply-To: ' . $replyTo;
        }

        if (!empty($context['cc_self'])) {
            $u = wp_get_current_user();
            if ($u && $u->user_email && is_email($u->user_email)) {
                $h[] = 'Bcc: ' . $u->user_email;
            }
        }

        return $h;
    }

    public function buildRecipients($to, array $context): array
    {
        $list = is_array($to) ? $to : explode(',', (string) $to);
        $list = array_map('trim', $list);
        $list = array_filter($list, static fn($s) => $s !== '');
        $list = array_map(static fn($s) => sanitize_email($s), $list);
        $list = array_filter($list, static fn($s) => (bool) is_email($s));
        return array_values(array_unique($list));
    }

    /**
     * ✅ PUBLIC so other code can reuse it if needed.
     * Renders BOTH modern {{...}} and legacy {...} placeholders.
     */
    public function renderPlaceholders(string $html, array $ctx): string
    {
        $userObj = isset($ctx['user']) && is_object($ctx['user']) ? $ctx['user'] : wp_get_current_user();

        $siteName = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $siteUrl  = home_url('/');

        $modern = [
            '{{site.name}}'         => $siteName,
            '{{site.url}}'          => $siteUrl,
            '{{form.id}}'           => (string) ($ctx['form_id'] ?? ''),
            '{{request.id}}'        => (string) ($ctx['request_id'] ?? ''),

            '{{user.id}}'           => $userObj ? (string) $userObj->ID : '',
            '{{user.email}}'        => $userObj ? (string) $userObj->user_email : '',
            '{{user.login}}'        => $userObj ? (string) $userObj->user_login : '',
            '{{user.display_name}}' => $userObj ? (string) $userObj->display_name : '',
        ];

        // Legacy aliases used elsewhere in the feature
        $legacy = [
            '{site_title}'   => $siteName,
            '{site_url}'     => $siteUrl,
            '{form_id}'      => (string) ($ctx['form_id'] ?? ''),
            '{request_id}'   => (string) ($ctx['request_id'] ?? ''),

            '{user_email}'   => $userObj ? (string) $userObj->user_email : '',
            '{user_login}'   => $userObj ? (string) $userObj->user_login : '',
            '{display_name}' => $userObj ? (string) $userObj->display_name : '',
        ];

        // One-pass replace for both sets
        return strtr($html, $modern + $legacy);
    }
}
