<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Emails;

use Yardlii\Core\Features\TrustVerification\Caps;
use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;

final class SendAllTestsAjax
{
    public function register(): void
    {
        add_action('wp_ajax_yardlii_tv_send_all_tests', [$this, 'handle']);
    }

    public function handle(): void
    {
        // Must match AdminPage.php -> YardliiTV.nonceSend
        check_ajax_referer('yardlii_tv_send_test', '_ajax_nonce');

        if (! current_user_can(Caps::MANAGE)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'yardlii-core')], 403);
        }

        $to   = isset($_POST['to'])   ? sanitize_email(wp_unslash((string) $_POST['to']))   : '';
        $type = isset($_POST['type']) ? sanitize_key((string) $_POST['type'])               : 'approve';

        if (empty($to) || !is_email($to)) {
            wp_send_json_error(['message' => __('Please provide a valid recipient address.', 'yardlii-core')]);
        }
        if (!in_array($type, ['approve','reject','both'], true)) {
            wp_send_json_error(['message' => __('Invalid test type.', 'yardlii-core')]);
        }

        $configs = (array) get_option(TVFormConfigs::OPT_KEY, []);
        if (empty($configs)) {
            wp_send_json_error(['message' => __('No form configurations found.', 'yardlii-core')]);
        }

        $mailer = new Mailer();
        $me     = wp_get_current_user();
        $sent = 0; $attempts = 0;

        foreach ($configs as $row) {
            $formId = (string) ($row['form_id'] ?? '');
            if ($formId === '') continue;

            $targets = ($type === 'both') ? ['approve', 'reject'] : [$type];
            foreach ($targets as $t) {
                $attempts++;

                $subject = ($t === 'approve')
                    ? (string) ($row['approve_subject'] ?? sprintf(__('You’re verified at %s', 'yardlii-core'), '{{site.name}}'))
                    : (string) ($row['reject_subject']  ?? sprintf(__('Your verification status at %s', 'yardlii-core'), '{{site.name}}'));

                $body = ($t === 'approve')
                    ? (string) ($row['approve_body'] ?? '<p>' . sprintf(__('Hi %s, your verification at %s was approved.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>')
                    : (string) ($row['reject_body']   ?? '<p>' . sprintf(__('Hi %s, we could not approve your verification at %s.', 'yardlii-core'), '{{user.display_name}}', '{{site.name}}') . '</p>');

                $ctx = [
                    'user'     => $me,
                    'form_id'  => $formId,
                    'reply_to' => ($me && is_email($me->user_email)) ? $me->user_email : get_bloginfo('admin_email'),
                    'cc_self'  => false,
                ];

                if ($mailer->send($to, $subject, $body, $ctx)) {
                    $sent++;
                }
            }
        }

        wp_send_json_success([
            'message'  => sprintf(__('Sent %1$d of %2$d test email(s).', 'yardlii-core'), (int) $sent, (int) $attempts),
            'sent'     => (int) $sent,
            'attempts' => (int) $attempts,
        ]);
    }
}
