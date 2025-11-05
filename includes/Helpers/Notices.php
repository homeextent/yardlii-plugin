<?php
namespace Yardlii\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * YARDLII — Central, page-scoped notices for the settings UI.
 *
 * Quick usage:
 *   Notices::ok('Saved!');
 *   Notices::error('Something went wrong', 'unique_code');
 *   Notices::stash('Background task completed'); // if called before page render
 *   Notices::render(); // call once near top of the plugin settings page
 *
 * Group (form) notices:
 *   // In panel template:
 *   Notices::group('yardlii_google_map_group'); // prints settings_errors for that group
 *   // Or multiple:
 *   Notices::groups(['yardlii_search_group','yardlii_general_group']);
 */
final class Notices
{
    /** Slug for page-scoped notices (separate from settings groups) */
    public const SLUG = 'yardlii_core';

    /** Page slug where these notices should render */
    public const PAGE = 'yardlii-core-settings';

    /** Transient key used by stash() */
    private const STASH_KEY = self::SLUG . '_notices';

    /** One-shot guard so render() doesn’t print twice */
    private static bool $did_render = false;

    /** True if we’re on the YARDLII settings page */
    public static function is_settings_page(): bool
    {
        return is_admin()
            && isset($_GET['page'])
            && $_GET['page'] === self::PAGE;
    }

    /**
     * Queue a page-scoped notice (prints inside our settings page via render()).
     * @param string      $message
     * @param 'updated'|'error'|'warning'|'info' $type
     * @param string|null $code Unique code; if omitted we hash the message+type
     */
    public static function queue(string $message, string $type = 'updated', ?string $code = null): void
    {
        add_settings_error(self::SLUG, $code ?: md5($message . $type), $message, $type);
    }

    /**
     * Stash a notice if it’s too early to print (e.g., in an init hook).
     * We’ll flush it in render().
     */
    public static function stash(string $message, string $type = 'updated', ?string $code = null): void
    {
        $list   = get_transient(self::STASH_KEY);
        $list   = is_array($list) ? $list : [];
        $list[] = ['m' => $message, 't' => $type, 'c' => $code];
        set_transient(self::STASH_KEY, $list, 60);
    }

    /**
     * Print all page-scoped notices and flush any stashed ones.
     * Call this ONCE near the top of the plugin page (after the header).
     */
    public static function render(): void
    {
        if (self::$did_render || ! self::is_settings_page()) {
            return;
        }
        self::$did_render = true;

        // Print any queued notices added via queue()
        if (function_exists('settings_errors')) {
            settings_errors(self::SLUG);
        }

        // Flush stashed notices (if any), then print again
        $list = get_transient(self::STASH_KEY);
        if ($list && is_array($list)) {
            foreach ($list as $n) {
                $code = $n['c'] ?: md5($n['m'] . $n['t'] . 't');
                add_settings_error(self::SLUG, $code, (string) $n['m'], (string) $n['t']);
            }
            delete_transient(self::STASH_KEY);

            if (function_exists('settings_errors')) {
                settings_errors(self::SLUG);
            }
        }
    }

    /**
     * Convenience: print settings_errors() for a specific settings group
     * (the group key used in register_setting). Safe to call multiple times.
     */
    public static function group(string $group): void
    {
        if (! function_exists('settings_errors')) return;
        settings_errors($group);
    }

    /**
     * Convenience: print multiple groups in one go.
     * Example: Notices::groups([self::GROUP_GOOGLE_MAP, self::GROUP_FEATURED_IMAGE]);
     */
    public static function groups(array $groups): void
    {
        foreach ($groups as $g) {
            self::group((string) $g);
        }
    }

    /* ---------- Ergonomic helpers ---------- */

    public static function ok(string $message, ?string $code = null): void
    {
        self::queue($message, 'updated', $code);
    }

    public static function error(string $message, ?string $code = null): void
    {
        self::queue($message, 'error', $code);
    }

    public static function warn(string $message, ?string $code = null): void
    {
        self::queue($message, 'warning', $code);
    }

    public static function info(string $message, ?string $code = null): void
    {
        self::queue($message, 'info', $code);
    }
}
