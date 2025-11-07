<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

use Yardlii\Core\Features\TrustVerification\UI\AdminPage;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Requests\Decisions;
// NEW: Import the registry
use Yardlii\Core\Features\TrustVerification\TvProviderRegistry;

final class Module
{
    private ?string $pluginFile = null;
    private ?string $version = null;

    public function __construct(
        ?string $pluginFile = null,
        ?string $version = null
    ) {
        $this->pluginFile = $pluginFile ?? (defined('YARDLII_CORE_FILE') ? YARDLII_CORE_FILE : __FILE__);
        $this->version = $version ?? (defined('YARDLII_CORE_VERSION') ? YARDLII_CORE_VERSION : null);
    }

    public function register(): void
    {
        //---- Capability seeding ----
        if (class_exists(\Yardlii\Core\Features\TrustVerification\Caps::class)) {
            add_action('admin_init', static function () {
                if (!get_option('yardlii_tv_cap_seeded')) {
                    \Yardlii\Core\Features\TrustVerification\Caps::grantDefault();
                    update_option('yardlii_tv_cap_seeded', 1, false);
                }
            });
        }

        // --- UI ---
        if (class_exists(AdminPage::class)) {
            (new AdminPage($this->pluginFile, $this->version))->register();
        }

        // --- Manual Dependency Injection for Decisions ---
        $mailer = class_exists(Mailer::class) ? new Mailer() : null;

        $decisionService = ($mailer && class_exists(TvDecisionService::class))
            ? new TvDecisionService($mailer)
            : null;

        if ($decisionService && class_exists(Decisions::class)) {
            try {
                // Use the backward-compatible constructor
                $decisionsController = new Decisions($decisionService);
                if (method_exists($decisionsController, 'register')) {
                    $decisionsController->register();
                }
            } catch (\Throwable $e) {
                if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
                    error_log('[YARDLII] Failed to register Decisions controller: ' . $e->getMessage());
                }
            }
        }

        // --- Storage / Workflow ---
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\CPT::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Guards::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Cleanup::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\HistoryAjax::class);

        // --- Emails ---
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\PreviewAjax::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendTestAjax::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendAllTestsAjax::class);

        // --- Tools / API ---
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Export\CsvController::class);
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Rest\StatusController::class);

        // --- Configuration / Global Settings ---
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings::class);

        // --- User update guards ---
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Support\UserDataGuards::class);

        // --- NEW: Boot Providers via Registry ---
        // This replaces the old bootProviders() method
        if (class_exists(TvProviderRegistry::class)) {
            $registry = new TvProviderRegistry();
            
            // Register our known providers
            $registry->registerProvider('wpuf', \Yardlii\Core\Features\TrustVerification\Providers\WPUF::class);
            $registry->registerProvider('elementor', \Yardlii\Core\Features\TrustVerification\Providers\ElementorPro::class);

            // Boot them
            $registry->boot();
        }
    }

    //
    // DELETED: bootProviders(), onSubmit(), onAfterSend() are all gone
    //

    private function safeRegister(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }
        try {
            $obj = new $fqcn();
            if (method_exists($obj, 'register')) {
                $obj->register();
            }
        } catch (\Throwable $e) {
            if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
                error_log('[YARDLII] safeRegister failed for ' . $fqcn . ': ' . $e->getMessage());
            }
        }
    }
}