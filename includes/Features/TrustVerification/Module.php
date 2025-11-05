<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification;

use Yardlii\Core\Features\TrustVerification\UI\AdminPage;

final class Module
{
    public function __construct(
        private ?string $pluginFile = null,
        private ?string $version    = null
    ) {
        $this->pluginFile = $pluginFile ?? (defined('YARDLII_CORE_FILE') ? YARDLII_CORE_FILE : __FILE__);
        $this->version    = $version    ?? (defined('YARDLII_CORE_VERSION') ? YARDLII_CORE_VERSION : null);
    }

    public function register(): void
{
    // ---- Capability seeding: one-time, per site, safe + idempotent ----
    if (class_exists(\Yardlii\Core\Features\TrustVerification\Caps::class)) {
        add_action('admin_init', static function () {
            // Only run once per site to avoid needless work every admin request
            if (! get_option('yardlii_tv_cap_seeded')) {
                \Yardlii\Core\Features\TrustVerification\Caps::grantDefault();
                update_option('yardlii_tv_cap_seeded', 1, false);
            }
        });
    }

    // UI first (adds tab + enqueues assets via AdminPage)
    if (class_exists(AdminPage::class)) {
        (new AdminPage($this->pluginFile, $this->version))->register();
    }

    // Storage / Workflow
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\CPT::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Guards::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Decisions::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\Cleanup::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Requests\HistoryAjax::class);

    // Emails
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\PreviewAjax::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\Mailer::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendTestAjax::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Emails\SendAllTestsAjax::class);

    // Tools / API
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Export\CsvController::class);
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Rest\StatusController::class);

    // Configuration / Global Settings
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Settings\GlobalSettings::class);

    // User update guards (prevent bogus email changes and blanks)
    $this->safeRegister(\Yardlii\Core\Features\TrustVerification\Support\UserDataGuards::class);

    // Providers layer (decouples from WPUF)
    $this->bootProviders();

    


    

}
// ---- replace your bootProviders() with:
private function bootProviders(): void
{
    $enabled = apply_filters('yardlii_tv_enabled_providers', [
        'wpuf'      => true,
        'elementor' => true,
    ]);

    // WPUF
    if (!empty($enabled['wpuf']) && class_exists('\Yardlii\Core\Features\TrustVerification\Providers\WPUF')) {
        try { (new \Yardlii\Core\Features\TrustVerification\Providers\WPUF())->registerHooks(); } catch (\Throwable $e) {}
    }

    // Elementor — register NOW if already initialized, otherwise defer until it is.
    if (!empty($enabled['elementor'])) {
        $register = static function () {
            if (class_exists('\Yardlii\Core\Features\TrustVerification\Providers\ElementorPro')) {
                static $done = false;
                if ($done) { return; } // avoid double registration
                $done = true;
                try { (new \Yardlii\Core\Features\TrustVerification\Providers\ElementorPro())->registerHooks(); } catch (\Throwable $e) {}
            }
        };

        if (did_action('elementor_pro/init')) {
            $register();
        } else {
            add_action('elementor_pro/init', $register, 0);
        }
    }
}
public function onSubmit($record, $handler): void {
    error_log('[TV] Elementor onSubmit fired');
    $this->forward($record, 'new_record');
}
public function onAfterSend($record, $handler): void {
    error_log('[TV] Elementor onAfterSend fired');
    $this->forward($record, 'after_send');
}



    private function safeRegister(string $fqcn): void
    {
        if (!class_exists($fqcn)) return;
        try {
            $obj = new $fqcn();
            if (method_exists($obj, 'register')) {
                $obj->register();
            }
        } catch (\Throwable $e) {
            // swallow — your global debug toggle can log this if needed
        }
    }
}
