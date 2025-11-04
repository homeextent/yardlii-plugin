<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Settings;

final class GlobalSettings
{
    public const OPT_GROUP          = 'yardlii_tv_global_group';
    public const OPT_EMAILS         = 'yardlii_tv_admin_emails';
    public const OPT_VERIFIED_ROLES = 'yardlii_tv_verified_roles';

   

    public function registerSettings(): void
    {
        register_setting(self::OPT_GROUP, self::OPT_EMAILS, [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitizeEmails'],
        ]);

        register_setting(self::OPT_GROUP, self::OPT_VERIFIED_ROLES, [
            'type'              => 'array',
            'default'           => [],    
            'sanitize_callback' => [$this, 'sanitizeRoleArray'],
        ]);

        register_setting('yardlii_tv_global_group', 'yardlii_tv_global', [
  'sanitize_callback' => function($value) {
    // … validate …
    if ($some_error) {
      add_settings_error(
        'yardlii_tv_global_group',   // <— matches settings_errors() above
        'invalid_global',
        __('Please select a valid Approved Role.', 'yardlii-core'),
        'error'
      );
      // return the old value to prevent save on error:
      return get_option('yardlii_tv_global', []);
    }
    add_settings_error('yardlii_tv_global_group', 'updated_global',
      __('Settings saved.', 'yardlii-core'), 'updated');
    return $value;
  },
]);

       

    }

    /** "a@b.com, c@d.com" -> normalized comma list of valid emails */
    public function sanitizeEmails($raw): string
    {
        $parts = array_map('trim', explode(',', (string) $raw));
        $valid = array_values(array_filter(array_map('sanitize_email', $parts), 'is_email'));
        return implode(', ', array_unique($valid));
    }

    /** keep only real WP role slugs */
    public function sanitizeRoleArray($input): array
    {
        if (!is_array($input)) return [];
        $known = array_keys(wp_roles()->roles ?? []);
        $out   = [];
        foreach ($input as $r) {
            $r = sanitize_key($r);
            if (in_array($r, $known, true)) $out[] = $r;
        }
        return array_values(array_unique($out));
    }
}
