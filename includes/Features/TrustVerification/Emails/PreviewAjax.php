<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

use Yardlii\Core\Features\TrustVerification\Caps;

final class PreviewAjax
{
    public function register(): void
    {
        add_action('wp_ajax_yardlii_tv_preview_email', [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer('yardlii_tv_preview', '_ajax_nonce');

        // Dedicated capability
        if ( ! current_user_can(Caps::MANAGE) ) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'yardlii-core')], 403);
        }

        $form_id = isset($_POST['form_id'])    ? sanitize_text_field( wp_unslash($_POST['form_id']) ) : '';
        $type    = isset($_POST['email_type']) ? sanitize_key(        wp_unslash($_POST['email_type']) ) : '';
        $user_id = isset($_POST['user_id'])    ? absint(              wp_unslash($_POST['user_id']) ) : 0;

        if ($form_id === '' || ! in_array($type, ['approve','reject'], true)) {
            wp_send_json_error(['message' => 'Missing or invalid parameters.']);
        }

        $config = Templates::findConfigByFormId($form_id);
        if (!$config) {
            wp_send_json_error(['message' => 'Form configuration not found.']);
        }

        // Load stored subject/body for this form/type
        $subject = ($type === 'approve')
            ? (string) ($config['approve_subject'] ?? '')
            : (string) ($config['reject_subject']  ?? '');

        $body = ($type === 'approve')
            ? (string) ($config['approve_body'] ?? '')
            : (string) ($config['reject_body']  ?? '');

        // Optional overrides from the UI (unsaved edits)
        $subject_override = isset($_POST['subject']) ? sanitize_text_field( wp_unslash($_POST['subject']) ) : '';
        $body_override    = isset($_POST['body'])    ? wp_kses_post(        wp_unslash($_POST['body']) )    : '';

        if ($subject_override !== '') { $subject = $subject_override; }
        if ($body_override    !== '') { $body    = $body_override; }

        if ($body === '') {
            wp_send_json_error(['message' => 'Empty template. Add body text and save settings.']);
        }

        // Build context and merge placeholders
        $ctx = Templates::buildContext($user_id ?: get_current_user_id(), $form_id, 0);

        // Merge body
        $html = Templates::mergePlaceholders($body, $ctx);

        // Merge subject and prepend it visually to the preview
        $subject = Templates::mergePlaceholders($subject, $ctx);
        $html    = '<h3 style="margin:0 0 10px;">' . esc_html($subject) . '</h3>' . $html;

        // Return sanitized preview HTML
        $allowed = wp_kses_allowed_html('post');
        wp_send_json_success(['html' => wp_kses($html, $allowed)]);
    }
}
