<?php
namespace Yardlii\Core\Helpers;

class Sanitization
{
    public static function text($value): string
    {
        return sanitize_text_field((string) $value);
    }
}
