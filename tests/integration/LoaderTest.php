<?php
declare(strict_types=1);

use Yardlii\Core\Features\Loader;

/**
 * Smoke test for the Features\Loader class.
 *
 * @covers \Yardlii\Core\Features\Loader
 * @group integration
 */
class LoaderTest extends WP_UnitTestCase
{
    /**
     * Set up the test environment before each test.
     * We will explicitly disable all known feature flags.
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Set all known feature flags to 'false' or '0'
        update_option('yardlii_enable_trust_verification', false); // [cite: 1470]
        update_option('yardlii_enable_acf_user_sync', false); // [cite: 1500]
        update_option('yardlii_enable_role_control', false); // [cite: 1513]
        update_option('yardlii_enable_wpuf_dropdown', false); // [cite: 1508]
        
        // Also disable sub-features, just in case
        update_option('yardlii_enable_role_control_submit', false); // [cite: 1518]
        update_option('yardlii_enable_custom_roles', false); // [cite: 1525]
        update_option('yardlii_enable_badge_assignment', false); // [cite: 1531]
    }

    /**
     * Test that if feature flags are off, their corresponding
     * modules and hooks are not registered.
     */
    public function test_hooks_are_not_registered_when_flags_are_off()
    {
        // Instantiate and run the loader
        (new Loader())->register();

        // The 'yardlii_tv_history_load' AJAX hook is registered by HistoryAjax[cite: 352],
        // which is loaded by TrustVerification\Module[cite: 1582].
        // The Module is only loaded by Loader if the 'yardlii_enable_trust_verification'
        // flag is true [cite: 1474-1478].
        
        // We assert that this hook (and others) are NOT registered.
        $this->assertFalse(
            (bool) has_action('wp_ajax_yardlii_tv_history_load'),
            'The TV History AJAX hook should not be registered when the main TV flag is off.'
        );
        
        $this->assertFalse(
            (bool) has_action('wp_ajax_yardlii_tv_preview_email'),
            'The TV Preview AJAX hook should not be registered when the main TV flag is off.'
        );
        
        $this->assertFalse(
            (bool) has_action('wp_ajax_yardlii_tv_send_test_email'),
            'The TV Send Test AJAX hook should not be registered when the main TV flag is off.'
        );
    }
}