<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

use Yardlii\Core\Features\TrustVerification\Providers\TvProviderInterface;
use Throwable;

/**
 * Manages the registration and booting of Trust & Verification form providers
 * (e.g., WPUF, Elementor).
 *
 * This replaces the hard-coded logic in Module.php
 */
final class TvProviderRegistry
{
    /**
     * @var array<string, string> Map of provider keys to their fully-qualified class names.
     */
    private array $providers = [];

    /**
     * Registers a potential provider class.
     *
     * @param string $key  A unique key for the provider (e.g., 'wpuf', 'elementor').
     * @param string $fqcn The fully-qualified class name (must implement TvProviderInterface).
     */
    public function registerProvider(string $key, string $fqcn): void
    {
        $this->providers[$key] = $fqcn;
    }

    /**
     * Boots all registered providers that are enabled.
     * This is called by Module.php.
     */
    public function boot(): void
    {
        $enabled = (array) apply_filters('yardlii_tv_enabled_providers', [
            'wpuf' => true,
            'elementor' => true,
        ]);

        foreach ($this->providers as $key => $fqcn) {
            // Check if this provider is enabled in the filter
            if (empty($enabled[$key])) {
                continue;
            }

            if (!class_exists($fqcn)) {
                continue;
            }

            // Ensure it implements the interface
            $implements = class_implements($fqcn);
            if (!$implements || !isset($implements[TvProviderInterface::class])) {
                if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
                    error_log("[YARDLII] Provider {$fqcn} does not implement TvProviderInterface.");
                }
                continue;
            }

            $this->bootProvider($fqcn);
        }
    }

    /**
     * Instantiates and registers hooks for a single provider.
     * Special-cased for Elementor Pro's deferred init.
     */
    private function bootProvider(string $fqcn): void
    {
        $registerClosure = static function () use ($fqcn) {
            static $done = [];
            if (!empty($done[$fqcn])) {
                return; // Avoid double registration
            }
            $done[$fqcn] = true;

            try {
                /** @var TvProviderInterface $instance */
                $instance = new $fqcn();
                $instance->registerHooks();
            } catch (Throwable $e) {
                if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
                    error_log("[YARDLII] Failed to boot provider {$fqcn}: " . $e->getMessage());
                }
            }
        };

        // Defer Elementor Pro until its 'init' action fires
        if (str_contains($fqcn, 'ElementorPro')) {
            if (did_action('elementor_pro/init')) {
                $registerClosure();
            } else {
                add_action('elementor_pro/init', $registerClosure, 0);
            }
        } else {
            // Register all other providers (like WPUF) immediately
            $registerClosure();
        }
    }
}