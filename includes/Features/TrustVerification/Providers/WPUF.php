<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;

final class WPUF implements ProviderInterface {
    public function getName(): string { return 'wpuf'; }
    public function registerHooks(): void {
        add_action('wpuf_update_profile', [$this, 'handle'], 10, 2);
        add_action('wpuf_after_register', [$this, 'handle'], 10, 2);
    }
    public function handle(int $user_id, $form_id): void {
        \Yardlii\Core\Features\TrustVerification\Requests\Guards::maybeCreateRequest($user_id, (string) $form_id, [
            'provider' => 'wpuf',
            'event'    => current_filter(),
        ]);
    }
}

