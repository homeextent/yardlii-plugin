<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

final class ElementorPro implements ProviderInterface
{
    public function getName(): string { return 'elementor-pro'; }

    public function registerHooks(): void
    {
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            error_log('Elementor provider hooks registered');
        }
        // Fire on both events (EP fires new_record before actions, after_send after actions)
        add_action('elementor_pro/forms/new_record', [$this, 'onSubmit'], 10, 2);
        add_action('elementor_pro/forms/after_send', [$this, 'onAfterSend'], 10, 2);
    }

    public function onSubmit($record, $handler): void
    {
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            error_log('Elementor onSubmit fired');
        }
        $this->forward($record, 'new_record');
    }

    public function onAfterSend($record, $handler): void
    {
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            error_log('Elementor onAfterSend fired');
        }
        $this->forward($record, 'after_send');
    }

    private function forward($record, string $event): void
    {
        if (!is_user_logged_in()) return;
        $user_id = (int) get_current_user_id();

        $form_id = "";
        // 1) Hidden field override (preferred)
        $fields = (array) ($record->get('fields') ?? []);
        if (isset($fields['tv_form_id']['value']) && $fields['tv_form_id']['value'] !== "") {
            $form_id = (string) $fields['tv_form_id']['value'];
        }

        // 2) If empty, derive from form "name" or internal "id"
        if ($form_id === '') {
            // Try $record->get('form_name') first (works on many versions)
            $name = (string) ($record->get('form_name') ?? "");
            // If still empty, safely query settings by key (your EP needs exactly 1 arg)
            if ($name === "" && method_exists($record, 'get_form_settings')) {
                try {
                    $name = (string) ($record->get_form_settings('form_name') ?? "");
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Also try the widget's internal id
            $wid = "";
            if (method_exists($record, 'get_form_settings')) {
                try {
                    $wid = (string) ($record->get_form_settings('id') ?? "");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            if ($wid === "") {
                $wid = (string) ($record->get('id') ?? "");
            }

            // Build the TV form_id
            if ($name !== "") {
                $form_id = 'elementor:' . sanitize_title($name);
            } elseif ($wid !== "") {
                $form_id = 'elementor:' . sanitize_title($wid);
            }
        }
        
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            error_log(sprintf('[TV] Elementor submit event=%s user=%d form_id=%s', $event, $user_id, $form_id));
        }

        if ($form_id === "") return;
        // Create/reuse the TV request
        \Yardlii\Core\Features\TrustVerification\Requests\Guards::maybeCreateRequest($user_id, $form_id, [
            'provider' => 'elementor',
            'event' => $event,
        ]);
    }
}