<?php
namespace Yardlii\Core\Helpers;

class Options
{
    public static function get_string(string $key, string $default = ''): string
    {
        $val = get_option($key, $default);
        return is_string($val) ? $val : $default;
    }

    public static function get_int(string $key, int $default = 0): int
    {
        return (int) get_option($key, $default);
    }
}
