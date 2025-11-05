<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Support;

final class Roles
{
    public static function setSingleRole(int $user_id, string $role): void
    {
        $u = new \WP_User($user_id);
        $u->set_role($role);
        do_action('yardlii_tv_after_role_change', $user_id, [$role], 'decision');
    }

    public static function restoreRoles(int $user_id, array $roles): void
    {
        $u = new \WP_User($user_id);
        if (!$roles) { $u->set_role('subscriber'); return; }
        $primary = array_shift($roles);
        $u->set_role($primary);
        foreach ($roles as $r) { if ($r !== $primary) $u->add_role($r); }
        do_action('yardlii_tv_after_role_change', $user_id, array_merge([$primary], $roles), 'reopen');
    }
}
