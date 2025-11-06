<?php
declare(strict_types=1);

/**
 * Test-only AJAX endpoint for the integration harness.
 * Action: 'yardlii_test_echo'
 */
add_action('wp_ajax_yardlii_test_echo', 'yardlii_test_ajax_echo');
add_action('wp_ajax_nopriv_yardlii_test_echo', 'yardlii_test_ajax_echo');

function yardlii_test_ajax_echo(): void {
    // Require a nonce named 'yardlii_test_nonce'.
    $nonce = $_POST['nonce'] ?? '';
    if (! $nonce || ! wp_verify_nonce($nonce, 'yardlii_test_nonce')) {
        wp_send_json_error(['code' => 'missing_or_bad_nonce']);
    }

    $payload = isset($_POST['payload']) ? (string) $_POST['payload'] : '';
    wp_send_json_success(['echo' => $payload]);
}
