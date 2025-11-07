<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

use Yardlii\Core\Features\TrustVerification\UI\AdminPage;

// --- NEW: Import classes for manual wiring ---
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Requests\Decisions;
// --- End new imports ---

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
        //---- Capability seeding: one-time, per site, safe + idempotent ----
        if (class_exists(\Yardlii\Core\Features\TrustVerification\Caps::class)) {
            add_action('admin_init', static function () {
                // Only run once per site to avoid needless work every admin request
                if (!get_option('yardlii_tv_cap_seeded')) { // [cite: 956]
                    \Yardlii\Core\Features\TrustVerification\Caps::grantDefault(); // [cite: 957]
                    update_option('yardlii_tv_cap_seeded', 1, false); // [cite: 958]
                }
            });
        }

        // UI first (adds tab + enqueues assets via AdminPage)
        if (class_exists(AdminPage::class)) { // [cite: 963]
            (new AdminPage($this->pluginFile, $this->version))->register(); // [cite: 965]
        }

        // --- NEW: Manual Dependency Injection for Decisions ---
        
        // 1. Mailer is a dependency for the service
        $mailer = class_exists(Mailer::class) ? new Mailer() : null;

        // 2. Decision Service needs Mailer
        $decisionService = ($mailer && class_exists(TvDecisionService::class))
            ? new TvDecisionService($mailer)
            : null;

        // 3. Decisions controller needs Decision Service
        if ($decisionService && class_exists(Decisions::class)) {
            try {
                $decisionsController = new Decisions($decisionService);
                if (method_exists($decisionsController, 'register')) {
                    $decisionsController->register(); // This hooks the admin actions
                }
            } catch (\Throwable $e) {
                if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
                    error_log('[YARDLII] Failed to register Decisions controller: ' . $e->getMessage());
                }
            }
        }
        // --- End Manual Injection ---


        // Storage / Workflow
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\CPT::class); // [cite: 967]
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Guards::class); // [cite: 968]
        // REMOVED: $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Decisions::class); 
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Cleanup::class); // 
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\HistoryAjax::class); // 

        // Emails
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\PreviewAjax::class); // [cite: 972]
        // REMOVED: $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\Mailer::class); [cite: 972]
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendTestAjax::class); // [cite: 973]
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendAllTestsAjax::class); // [cite: 973]

        // Tools / API
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Export\CsvController::class); // [cite: 975]
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Rest\StatusController::class); // [cite: 975]

        // Configuration / Global Settings
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings::class); // [cite: 977]

        // User update guards (prevent bogus email changes and blanks)
        $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Support\UserDataGuards::class); // [cite: 979]

        // Providers layer (decouples from WPUF)
        $this->bootProviders(); // [cite: 980]
    }

    private function bootProviders(): void
    {
        $enabled = apply_filters('yardlii_tv_enabled_providers', [ // [cite: 985]
            'wpuf' => true, // [cite: 986]
            'elementor' => true, // [cite: 987]
        ]);

        // WPUF
        if (
            !empty($enabled['wpuf']) &&
            class_exists('\Yardlii\Core\Features\TrustVerification\Providers\WPUF') // <-- FIX: Was WWPUF
        ) {
            try {
                (new \Yardlii\Core\Features\TrustVerification\Providers\WPUF())->registerHooks(); // <-- FIX: Was WWPUF
            } catch (\Throwable $e) {
            }
        }

        // Elementor
        if (!empty($enabled['elementor'])) { // [cite: 997]
            $register = static function () { // [cite: 998]
                if (class_exists('\Yardlii\Core\Features\TrustVerification\Providers\ElementorPro')) { // [cite: 999]
                    static $done = false;
                    if ($done) { return; } // [cite: 1000]
                    $done = true; // [cite: 1001]
                    try {
                        (new \Yardlii\Core\Features\TrustVerification\Providers\ElementorPro())->registerHooks(); // [cite: 1003]
                    } catch (\Throwable $e) {
                    }
                }
            };

            if (did_action('elementor_pro/init')) { // [cite: 1006]
                $register(); // [cite: 1007]
            } else {
                add_action('elementor_pro/init', $register, 0); // [cite: 1009]
            }
        }
    }
    
    // These methods were misplaced in the original file [cite: 1013-1019]
    public function onSubmit($record, $handler): void
    {
        error_log('[TV] Elementor onSubmit fired'); // [cite: 1014]
        // $this->forward($record, 'new_record'); // [cite: 1015] (forward method doesn't exist, commenting out)
    }

    public function onAfterSend($record, $handler): void
    {
        error_log('[TV] Elementor onAfterSend fired'); // [cite: 1017]
        // $this->forward($record, 'after_send'); // [cite: 1018] (forward method doesn't exist, commenting out)
    }


    private function safeRegister(string $fqcn): void
    {
        if (!class_exists($fqcn)) { // [cite: 1022]
            return;
        }
        try {
            $obj = new $fqcn(); // 
            if (method_exists($obj, 'register')) { // [cite: 1025]
                $obj->register(); // [cite: 1028]
            }
        } catch (\Throwable $e) {
            // swallow
            if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) { // [cite: 93]
                error_log('[YARDLII] safeRegister failed for ' . $fqcn . ': ' . $e->getMessage());
            }
        }
    }
}