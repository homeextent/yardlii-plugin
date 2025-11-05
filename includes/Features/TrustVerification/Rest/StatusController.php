<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Rest;

use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs;
use Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings;
use Yardlii\Core\Features\TrustVerification\Caps;

final class StatusController
{
    public function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('yardlii/v1', '/verification-status/(?P<user_id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'getStatus'],
                'permission_callback' => Caps::restPermission(),
            ]);
        });
    }

    public function getStatus(\WP_REST_Request $req)
    {
        $user_id = (int) $req['user_id'];
        $user = get_userdata($user_id);
        if (!$user) return new \WP_Error('not_found', 'User not found', ['status' => 404]);

        $roles = $user->roles ?? [];
        $verifiedRoles = $this->getVerifiedRoles();

        return rest_ensure_response([
            'user_id'     => $user_id,
            'roles'       => array_values($roles),
            'is_verified' => (bool) array_intersect($roles, $verifiedRoles),
        ]);
    }

    private function getVerifiedRoles(): array
    {
        $override = get_option(GlobalSettings::OPT_VERIFIED_ROLES, []);
        if (is_array($override) && $override) return $override;

        $configs = get_option(FormConfigs::OPT_KEY, []);
        $roles = [];
        foreach ($configs as $row) {
            if (!empty($row['approved_role'])) $roles[] = $row['approved_role'];
        }
        return array_values(array_unique(array_filter(array_map('sanitize_key', $roles))));
    }
}
