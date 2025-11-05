<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

use Stringable;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs;

final class Templates
{
    /**
     * @return array<string,mixed>|null
     */
    public static function findConfigByFormId(string $form_id): ?array
    {
        /** @var array<int,array<string,mixed>> $configs */
        $configs = (array) \get_option(FormConfigs::OPT_KEY, []);
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Build a legacy placeholder context (keys like "{site_title}").
     *
     * @return array<string,string>
     */
    public static function buildContext(?int $user_id, string $form_id, int $request_id = 0): array
{
    // Site title with WP fallback
    $rawTitle = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'Yardlii';
    $site_title = function_exists('wp_specialchars_decode')
        ? wp_specialchars_decode($rawTitle, ENT_QUOTES)
        : html_entity_decode($rawTitle, ENT_QUOTES);

    // Site URL with WP fallback
    $site_url = function_exists('home_url') ? (string) home_url('/') : '/';

    // User with WP fallback
    $user = ($user_id && function_exists('get_userdata')) ? get_userdata($user_id) : null;

    return [
        '{display_name}' => $user->display_name ?? 'Sample User',
        '{user_email}'   => $user->user_email   ?? 'sample@example.com',
        '{user_login}'   => $user->user_login   ?? 'sample_login',
        '{form_id}'      => $form_id,
        '{request_id}'   => (string) $request_id,
        '{site_title}'   => $site_title,
        '{site_url}'     => $site_url,
    ];
}


    /**
     * Replace {{dot.notation}} and legacy {token} placeholders.
     *
     * @param array<string,mixed> $ctx
     */
    public static function mergePlaceholders(string $html, array $ctx): string
    {
        // Normalize to a flat map supporting both dot-notated and {braced} keys.
        /** @var array<string,mixed> $map */
        $map = self::normalizeContext($ctx);

        // 1) {{dot.path}} — leave unknown tokens unchanged
        $html = (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/',
            static function (array $m) use ($map): string {
                $key = $m[1];
                $val = self::resolvePath($key, $map);
                return self::isStringable($val) ? (string) $val : $m[0];
            },
            $html
        );

        // 2) {legacy_token} — only replace with stringable values
        $replacements = [];
        foreach ($map as $k => $v) {
            if ($k !== '' && $k[0] === '{' && substr($k, -1) === '}' && self::isStringable($v)) {
                $replacements[$k] = (string) $v;
            }
        }

        return $replacements ? strtr($html, $replacements) : $html;
    }

    /**
     * Flatten nested arrays to "a.b.c" => value and also provide "{a.b.c}" keys.
     *
     * @param  array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    private static function normalizeContext(array $ctx): array
    {
        /** @var array<string,mixed> $flat */
        $flat = [];

        $walk = static function (array $node, string $prefix = '') use (&$flat, &$walk): void {
            foreach ($node as $k => $v) {
                $k = (string) $k;
                $path = $prefix === '' ? $k : $prefix . '.' . $k;

                if (is_array($v)) {
                    $walk($v, $path);
                } else {
                    $flat[$path] = $v;
                }
            }
        };

        $walk($ctx);

        // Add braced aliases for dot-keys and carry through any already-braced keys from input.
        foreach ($flat as $k => $v) {
            $flat['{' . $k . '}'] = $v;
        }
        foreach ($ctx as $k => $v) {
            $k = (string) $k;
            if ($k !== '' && $k[0] === '{' && substr($k, -1) === '}') {
                $flat[$k] = $v;
            }
        }

        return $flat;
    }

    /**
     * Resolve a flattened "a.b.c" key from the normalized map.
     *
     * @param  array<string,mixed> $context
     * @return mixed
     */
    private static function resolvePath(string $path, array $context)
    {
        return $context[$path] ?? null;
    }

    private static function isStringable(mixed $value): bool
{
    // Unknown key → null → keep token verbatim (do not replace)
    if ($value === null) {
        return false;
    }

    return is_scalar($value)
        || $value instanceof \Stringable
        || (is_object($value) && method_exists($value, '__toString'));
}

}
