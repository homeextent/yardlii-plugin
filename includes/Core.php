<?php
namespace Yardlii\Core;

use Yardlii\Core\Admin\SettingsPageTabs;
use Yardlii\Core\Admin\Assets;
use Yardlii\Core\Features\Loader;

class Core
{
    /**
     * Safe logging helper (only logs if WP_DEBUG = true)
     */
    private function log_init($message): void
{
    $debug_enabled = (bool) get_option('yardlii_debug_mode', false);

    if ((defined('WP_DEBUG') && WP_DEBUG === true) || $debug_enabled) {
        error_log('[YARDLII INIT] ' . $message);
    }
}


    /**
     * Main initialization method
     */
    public function init(): void
    {
        $this->load_textdomain();
        $this->log_init('Core::init() starting...');

        try {
            (new Assets())->register();
            $this->log_init('Admin assets registered successfully.');
        } catch (\Throwable $e) {
            $this->log_init('Error registering assets: ' . $e->getMessage());
        }

        try {
            (new SettingsPageTabs())->register();
            $this->log_init('SettingsPageTabs registered successfully.');
        } catch (\Throwable $e) {
            $this->log_init('Error initializing SettingsPageTabs: ' . $e->getMessage());
        }

        try {
            (new Loader())->register();
            $this->log_init('Feature loader registered successfully.');
        } catch (\Throwable $e) {
            $this->log_init('Error initializing Loader: ' . $e->getMessage());
        }

        $this->log_init('Core::init() completed.');
    }

    /**
     * Load translations
     */
    private function load_textdomain(): void
    {
        load_plugin_textdomain(
            'yardlii-core',
            false,
            dirname(plugin_basename(__FILE__), 2) . '/languages'
        );
    }
}
