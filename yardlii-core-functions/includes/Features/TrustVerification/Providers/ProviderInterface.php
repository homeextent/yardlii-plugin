<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

interface ProviderInterface {
    /** Human-readable provider name (for logs/debug). */
    public function getName(): string;

    /** Hook into the provider’s events and forward to Guards::maybeCreateRequest(). */
    public function registerHooks(): void;
}
