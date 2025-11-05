<?php
declare(strict_types=1);

use Yardlii\Core\Features\TrustVerification\Settings\FormConfigs as TVFormConfigs;

final class AjaxSendTestEmailTest extends WP_Ajax_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        update_option(TVFormConfigs::OPT_KEY, [
            [
                'form_id'          => 'form-ajax',
                'approve_subject'  => 'Approved for {site_title}',
                'approve_body'     => 'Hi {display_name}, welcome to {{site.name}}.',
                'reject_subject'   => 'Rejected at {site_title}',
                'reject_body'      => 'Sorry {display_name}.',
                'preview_to'       => 'devnull@example.com',
            ],
        ], false);
    }

    public function test_send_test_email_ajax_happy_path(): void
    {
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $_POST['_ajax_nonce'] = wp_create_nonce('yardlii_tv_send_test');
        $_POST['form_id']     = 'form-ajax';
        $_POST['email_type']  = 'approve';
        $_POST['to']          = 'rcpt@example.com';

        try {
            $this->_handleAjax('yardlii_tv_send_test_email');
        } catch (WPAjaxDieContinueException $e) {
            // expected; WP_Ajax_UnitTestCase captures output
        }

        $json = json_decode($this->_last_response, true);
        $this->assertIsArray($json);
        $this->assertTrue($json['success'] ?? false, 'Expected success=true JSON');
    }
}
