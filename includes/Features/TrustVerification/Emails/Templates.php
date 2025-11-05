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
    // Expose legacy {token} keys as plain keys too (e.g., "{site_title}" → "site_title")
    $context = self::normalizeContext($ctx);

    // 1) New {{ dot.notation }} tokens from nested context
    $html = preg_replace_callback(
        '/\{\{\s*([A-Za-z0-9_\.]+)\s*\}\}/',
        static function (array $m) use ($context) {
            $value = self::resolvePath($context, $m[1]);
            return self::isStringable($value) ? (string) $value : $m[0]; // leave as-is if not stringable
        },
        $html
    );

    // 2) Legacy {token} tokens (root only)
    $html = preg_replace_callback(
        '/\{([A-Za-z0-9_]+)\}/',
        static function (array $m) use ($context) {
            $key = $m[1];
            if (array_key_exists($key, $context) && self::isStringable($context[$key])) {
                return (string) $context[$key];
            }
            return $m[0]; // leave unknown/non-stringable tokens untouched
        },
        $html
    );

    // Do NOT escape; tests expect HTML to be preserved
    return $html;
}

/** Turn a legacy strtr()-style map into a plain array too: "{foo}" => "bar" also becomes ["foo" => "bar"] */
private static function normalizeContext(array $ctx): array
{
    $out = $ctx;
    foreach ($ctx as $k => $v) {
        if (is_string($k) && strlen($k) > 2 && $k[0] === '{' && substr($k, -1) === '}') {
            $out[substr($k, 1, -1)] = $v;
        }
    }
    return $out;
}

/** Resolve "a.b.c" paths into arrays/objects; null if not found. */
private static function resolvePath($context, string $path)
{
    $current = $context;
    foreach (explode('.', $path) as $part) {
        if (is_array($current) && array_key_exists($part, $current)) {
            $current = $current[$part];
        } elseif (is_object($current) && isset($current->{$part})) {
            $current = $current->{$part};
        } else {
            return null;
        }
    }
    return $current;
}

/** True if a value can be safely cast to string. */
private static function isStringable($value): bool
{
    return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
}



}
