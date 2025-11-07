<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

/**
 * Central capability definition + small helpers for Trust & Verification.
 *
 * Usage (in your plugin bootstrap):
 *   register_activation_hook(YARDLII_CORE_FILE, [\Yardlii\Core\Features\TrustVerification\Caps::class, 'grantDefault']);
 *   // Optional on deactivation:
 *   // register_deactivation_hook(YARDLII_CORE_FILE, [\Yardlii\Core\Features\TrustVerification\Caps::class, 'revokeDefault']);
 *
 * Checks throughout the module:
 *   if ( ! current_user_can(\Yardlii\Core\Features\TrustVerification\Caps::MANAGE) ) { ... }
 */
final class Caps
{
    /** Capability required to manage verification requests, emails, tools, etc. */
    public const MANAGE = 'manage_verifications';

    /**
     * Grant the capability to default roles.
     * Call this from your activation/migration routine.
     */
    public static function grantDefault(): void
    {
        // Grant to Administrators by default. Add 'editor' => true if desired.
        $grantTo = [
            'administrator' => true,
            // 'editor'        => true,
        ];

        foreach ($grantTo as $roleSlug => $enabled) {
            if (! $enabled) continue;
            $role = get_role($roleSlug);
            if ($role && ! $role->has_cap(self::MANAGE)) {
                $role->add_cap(self::MANAGE);
            }
        }
    }

    /**
     * Revoke the capability from roles (optional; call on deactivation if you prefer cleanup).
     */
    public static function revokeDefault(): void
    {
        $roles = [
            'administrator',
            // 'editor',
        ];

        foreach ($roles as $roleSlug) {
            $role = get_role($roleSlug);
            if ($role && $role->has_cap(self::MANAGE)) {
                $role->remove_cap(self::MANAGE);
            }
        }
    }

    /**
     * Convenience checker you can use in non-global contexts.
     */
    public static function userCanManage(?int $userld = null): bool
	{
		if ($userld === null) {
			// Check capability for the *current* user
			return current_user_can(self::MANAGE);
		}

		// Check capability for a *specific* user ID
		$user = get_user_by('id', $userld);
		return $user ? user_can($user, self::MANAGE) : false;
	}

    /**
     * Handy permission callback for REST routes:
     *   'permission_callback' => \Yardlii\Core\Features\TrustVerification\Caps::restPermission()
     */
    public static function restPermission(): callable
    {
        return static function (): bool {
            return current_user_can(self::MANAGE);
        };
    }
}
