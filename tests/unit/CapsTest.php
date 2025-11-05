<?php
declare(strict_types=1);

use Yardlii\Core\Features\TrustVerification\Caps;

final class CapsTest extends WP_UnitTestCase
{
    public function test_caps_seed_grants_manage_to_admin(): void
    {
        Caps::grantDefault();
        $role = get_role('administrator');

        $this->assertNotNull($role, 'administrator role missing');
        $this->assertTrue($role->has_cap(Caps::MANAGE), 'administrator should have TV manage cap');
    }
}
