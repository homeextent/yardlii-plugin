<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

/**
 * Interface for all Trust & Verification form providers.
 * Ensures each provider has a standard entry point for registration.
 */
interface TvProviderInterface
{
    /**
     * Registers all necessary hooks (actions, filters) for the provider.
     */
    public function registerHooks(): void;
}