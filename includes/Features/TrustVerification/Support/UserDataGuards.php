<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Support;

/**
 * Hardening around user updates:
 * - Don’t send the core "email changed" notice when the new email is empty or identical.
 * - Don’t allow user_email to be set to empty on updates (preserve current).
 */
final class UserDataGuards
{
    public function register(): void
    {
        // Suppress "email changed" email if the change is not meaningful
        add_filter('send_email_change_email', [$this, 'maybeSuppressEmailChangeNotice'], 10, 3);

        // Preserve existing email if an update tries to set it blank/invalid
        add_filter('wp_pre_insert_user_data', [$this, 'preserveEmailIfEmpty'], 10, 3);
    }

    /**
     * @param bool     $send     Core's default = true
     * @param \WP_User $user     Existing user object
     * @param array    $userdata The would-be new data (may contain 'user_email')
     */
    public function maybeSuppressEmailChangeNotice($send, $user, array $userdata): bool
{
    // $user can be an array (core) or a WP_User (just in case)
    $new = isset($userdata['user_email']) ? trim((string) $userdata['user_email']) : '';
    $old = '';

    if (is_array($user)) {
        $old = isset($user['user_email']) ? trim((string) $user['user_email']) : '';
        if ($old === '' && !empty($user['ID'])) {
            $u = get_userdata((int) $user['ID']);
            if ($u) $old = (string) $u->user_email;
        }
    } elseif ($user instanceof \WP_User) {
        $old = (string) $user->user_email;
    }

    // Suppress notice if new is empty or unchanged (case-insensitive)
    if ($new === '' || ($old !== '' && strcasecmp($new, $old) === 0)) {
        return false;
    }

    return (bool) $send;
}


    /**
     * @param array $data   The sanitized user data about to be saved
     * @param bool  $update Whether this is an update (true) or insert (false)
     * @param int   $id     The user ID (when $update === true)
     */
    public function preserveEmailIfEmpty(array $data, bool $update, $id): array
{
    if (!$update) return $data;

    $uid = is_array($id) ? (int) ($id['ID'] ?? 0) : (int) $id;
    if ($uid <= 0) return $data;

    if (array_key_exists('user_email', $data)) {
        $new = trim((string) $data['user_email']);

        // If new email is empty/invalid, keep the existing one (or drop the key)
        if ($new === '' || !is_email($new)) {
            $current = get_userdata($uid);
            if ($current && $current->user_email) {
                $data['user_email'] = $current->user_email;
            } else {
                unset($data['user_email']); // last resort: avoid writing empty
            }
        }
    }

    return $data;
}

}
