<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs;

final class Templates
{
    public static function findConfigByFormId(string $form_id): ?array
    {
        $configs = (array) get_option(FormConfigs::OPT_KEY, []);
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) {
                return $row;
            }
        }
        return null;
    }

    public static function buildContext(?int $user_id, string $form_id, int $request_id = 0): array
    {
        $site_title = wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES );
        $site_url   = home_url('/');
        $user = $user_id ? get_userdata($user_id) : null;

        return [
            '{display_name}' => $user?->display_name ?: 'Sample User',
            '{user_email}'   => $user?->user_email   ?: 'sample@example.com',
            '{user_login}'   => $user?->user_login   ?: 'sample_login',
            '{form_id}'      => $form_id,
            '{request_id}'   => (string) $request_id,
            '{site_title}'   => $site_title,
            '{site_url}'     => $site_url,
        ];
    }

    public static function mergePlaceholders(string $html, array $ctx): string
    {
        // Simple, predictable replacement
        return strtr($html, $ctx);
    }
}
