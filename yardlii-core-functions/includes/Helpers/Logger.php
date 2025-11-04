<?php
namespace Yardlii\Core\Helpers;

class Logger
{
    public static function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[YARDLII] ' . $message);
        }
    }
}
