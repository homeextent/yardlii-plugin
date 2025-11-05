<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Settings;

final class FormConfigs
{
    public const OPT_GROUP = 'yardlii_tv_form_configs_group';
    public const OPT_KEY   = 'yardlii_tv_form_configs';

    /** Row keys we persist (prevents option bloat) */
    private const ROW_KEYS = [
        'form_id',
        'approved_role',
        'rejected_role',
        'approve_subject',
        'approve_body',
        'reject_subject',
        'reject_body',
    ];

    public function registerSettings(): void
    {
        register_setting(self::OPT_GROUP, self::OPT_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeFormConfigs'],
        ]);
    }

    /**
     * Validate each per-form config row and emit inline, group-scoped notices.
     * - Skips rows with empty form_id
     * - Blocks duplicates (case-insensitive) and invalid roles
     * - Returns the previous value on error to prevent saving bad data
     */
    public function sanitizeFormConfigs($input): array
    {
        $prev     = get_option(self::OPT_KEY, []);
        $group    = self::OPT_GROUP;
        $hadError = false;

        if (!is_array($input)) {
            add_settings_error(
                $group,
                'invalid_payload',
                __('Invalid configuration payload.', 'yardlii-core'),
                'error'
            );
            return $prev; // prevent save
        }

        // Known roles (slugs)
        $wp_roles   = wp_roles();
        $knownRoles = is_object($wp_roles) ? array_keys($wp_roles->roles ?? []) : [];

        $seenFormIds = []; // case-insensitive dedupe
        $out         = [];

        foreach ($input as $i => $row) {
            if (!is_array($row)) {
                continue; // silently ignore non-array rows
            }

            // --- form_id (required per row) ---
            $form_id_raw = (string)($row['form_id'] ?? '');
            $form_id     = trim($form_id_raw);

            if ($form_id === '') {
                // Allow visually present “empty” rows to be ignored without error
                continue;
            }

            // Case-insensitive uniqueness (avoids "Form" vs "form" duplicates)
            $form_key = mb_strtolower($form_id);
            if (isset($seenFormIds[$form_key])) {
                $hadError = true;
                add_settings_error(
                    $group,
                    'duplicate_form_id_' . sanitize_key($form_id),
                    sprintf(
                        __('Duplicate Form ID: %s. Each form must have a unique Form ID.', 'yardlii-core'),
                        esc_html($form_id)
                    ),
                    'error'
                );
                // continue to collect all errors, but do not add this row
                continue;
            }
            $seenFormIds[$form_key] = true;

            // --- roles (optional; must be valid if provided) ---
            $approved = sanitize_key($row['approved_role'] ?? '');
            $rejected = sanitize_key($row['rejected_role'] ?? '');

            if ($approved && !in_array($approved, $knownRoles, true)) {
                $hadError = true;
                add_settings_error(
                    $group,
                    'invalid_approved_' . sanitize_key($form_id),
                    sprintf(
                        __('Invalid Approved Role "%1$s" for Form ID %2$s.', 'yardlii-core'),
                        esc_html($approved),
                        esc_html($form_id)
                    ),
                    'error'
                );
            }

            if ($rejected && !in_array($rejected, $knownRoles, true)) {
                $hadError = true;
                add_settings_error(
                    $group,
                    'invalid_rejected_' . sanitize_key($form_id),
                    sprintf(
                        __('Invalid Rejected Role "%1$s" for Form ID %2$s.', 'yardlii-core'),
                        esc_html($rejected),
                        esc_html($form_id)
                    ),
                    'error'
                );
            }

            // Advisory only: allow it, but warn (do NOT set $hadError)
if ($approved === 'pending_verification') {
    add_settings_error(
        $group,
        'approved_is_pending_' . sanitize_key($form_id),
        sprintf(__('Heads up: Form %s uses "pending_verification" as the Approved role. This is unusual but allowed.', 'yardlii-core'), esc_html($form_id)),
        'notice-warning'
    );
}


            // --- subjects & bodies ---
            $approve_subject = sanitize_text_field($row['approve_subject'] ?? '');
            $reject_subject  = sanitize_text_field($row['reject_subject'] ?? '');
            $approve_body    = wp_kses_post($row['approve_body'] ?? '');
            $reject_body     = wp_kses_post($row['reject_body'] ?? '');

            // Build sanitized row with only the whitelisted keys
            $out[] = [
                'form_id'         => $form_id,
                'approved_role'   => ($approved && in_array($approved, $knownRoles, true)) ? $approved : '',
                'rejected_role'   => ($rejected && in_array($rejected, $knownRoles, true)) ? $rejected : '',
                'approve_subject' => $approve_subject,
                'reject_subject'  => $reject_subject,
                'approve_body'    => $approve_body,
                'reject_body'     => $reject_body,
            ];
        }

        // Hard errors → don’t persist; keep previous value
        if ($hadError) {
            add_settings_error(
                $group,
                'form_configs_not_saved',
                __('Some configurations are invalid. Please fix the errors above and save again.', 'yardlii-core'),
                'error'
            );
            return $prev;
        }

        // Normalize indices (0..n) and allow last-minute filters
        $out = array_values($out);
        /**
         * Filter the sanitized Trust & Verification form configs before save.
         *
         * @param array $out  Sanitized configs ready to save.
         */
        $out = apply_filters('yardlii_tv_form_configs_sanitized', $out);

        // Success banner (scoped to TV panel via settings_errors($group))
        add_settings_error(
            $group,
            'form_configs_saved',
            __('Settings saved.', 'yardlii-core'),
            'updated'
        );

        return $out;
    }
}
