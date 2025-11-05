<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

use Yardlii\Core\Features\TrustVerification\Caps;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;

final class SendTestAjax
{
    public function register(): void
    {
        add_action('wp_ajax_yardlii_tv_send_test_email', [$this, 'handle']);
    }

    public function handle(): void
    {
        // Must match AdminPage.php -> YardliiTV.nonceSend
        check_ajax_referer('yardlii_tv_send_test', '_ajax_nonce');

        if ( ! current_user_can(Caps::MANAGE) ) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'yardlii-core')], 403);
        }

        $form_id = isset($_POST['form_id'])    ? sanitize_text_field(wp_unslash((string) $_POST['form_id'])) : '';
        $type    = isset($_POST['email_type']) ? sanitize_key((string) $_POST['email_type'])                : '';
        $user_id = isset($_POST['user_id'])    ? absint($_POST['user_id'])                                  : 0;
        $to      = isset($_POST['to'])         ? sanitize_email(wp_unslash((string) $_POST['to']))          : '';

        if ($form_id === '' || ! in_array($type, ['approve','reject'], true)) {
            wp_send_json_error(['message' => __('Missing or invalid parameters.', 'yardlii-core')]);
        }
        if (empty($to) || ! is_email($to)) {
            wp_send_json_error(['message' => __('Please provide a valid recipient address.', 'yardlii-core')]);
        }

        // Find this form’s config row
        $config  = null;
        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []);
        foreach ($configs as $row) {
            if ((string) ($row['form_id'] ?? '') === $form_id) {
                $config = (array) $row;
                break;
            }
        }
        if (! $config) {
            wp_send_json_error(['message' => __('Form configuration not found.', 'yardlii-core')]);
        }

        // Stored subject/body or sensible defaults (use modern {{…}} tokens; legacy tokens still ok)
        $subject = ($type === 'approve')
            ? (string) ($config['approve_subject'] ?? sprintf(__('You’re verified at %s', 'yardlii-core'), '{{site.name}}'))
            : (string) ($config['reject_subject']  ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}'));

        $body = ($type === 'approve')
            ? (string) ($config['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>')
            : (string) ($config['reject_body']   ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>');

        // Allow unsaved overrides from the UI (what you typed in the row before Save)
        $subject_ov = isset($_POST['subject']) ? sanitize_text_field(wp_unslash((string) $_POST['subject'])) : '';
        $body_ov    = isset($_POST['body'])    ? wp_kses_post(wp_unslash((string) $_POST['body']))           : '';
        if ($subject_ov !== '') { $subject = $subject_ov; }
        if ($body_ov    !== '') { $body    = $body_ov; }

        if ($body === '') {
            wp_send_json_error(['message' => __('Empty template. Add body text and save settings.', 'yardlii-core')]);
        }

        // Context for centralized Mailer
        $member = $user_id ? get_userdata($user_id) : null;
        $me     = wp_get_current_user();
        $ctx = [
            // If a member is provided, tokens resolve to that user; else to current admin
            'user'     => ($member ?: $me),
            'form_id'  => $form_id,
            // For a test, make replies go back to the person running the test (reliable)
            'reply_to' => ($me && is_email($me->user_email)) ? $me->user_email : get_bloginfo('admin_email'),
            'cc_self'  => false,
        ];

        // Send via central Mailer (which also does {{token}} rendering and From/Reply-To)
        $ok = (new Mailer())->send($to, $subject, $body, $ctx);

        if (! $ok) {
            // Surface PHPMailer error if available
            $err = '';
            if (isset($GLOBALS['phpmailer']) && $GLOBALS['phpmailer'] instanceof \PHPMailer\PHPMailer\PHPMailer) {
                $err = $GLOBALS['phpmailer']->ErrorInfo ?: '';
            }
            wp_send_json_error(['message' => trim(__('Could not send email.', 'yardlii-core') . ' ' . $err)]);
        }

        wp_send_json_success(['message' => sprintf(__('Test email sent to %s', 'yardlii-core'), esc_html($to))]);
    }
}
