<?php

declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;
use WP_Error;

final class WPUF implements ProviderInterface
{
    public function getName(): string
    {
        return 'wpuf';
    }

    public function registerHooks(): void
    {
        // Processing Hooks (Existing)
        add_action('wpuf_update_profile', [$this, 'handle'], 10, 2);
        add_action('wpuf_after_register', [$this, 'handle'], 10, 2);

        // Validation Hooks
        
        // Scenario B: Registration (Standard WP_Error return works here)
        add_filter('wpuf_process_registration_errors', [$this, 'validateRegistration'], 10, 3);
        
        // Scenario A: Profile Update (We use the vars filter as a gateway check)
        add_filter('wpuf_update_profile_vars', [$this, 'validateProfileUpdate'], 10, 3);
    }

    /**
     * Scenario B: Registration Validation
     *
     * @param WP_Error             $errors
     * @param int                  $form_id
     * @param array<string, mixed> $form_settings
     * @return WP_Error
     */
    public function validateRegistration($errors, $form_id, $form_settings)
    {
        if (empty($_POST['yardlii_employer_email'])) {
            return $errors;
        }

        $applicantEmail = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $employerEmail  = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));

        if ($applicantEmail && strcasecmp($employerEmail, $applicantEmail) === 0) {
            $errors->add('self_vouch', __('You cannot verify yourself. Please enter a valid employer email.', 'yardlii-core'));
        }

        return $errors;
    }

    /**
     * Scenario A: Profile Update Validation "Kill Switch"
     * * We intercept the data preparation. If the user tries to self-vouch,
     * we immediately kill the process and send a JSON error.
     *
     * @param array<string, mixed> $userdata
     * @param int                  $form_id
     * @param array<string, mixed> $form_settings
     * @return array<string, mixed>
     */
    public function validateProfileUpdate($userdata, $form_id, $form_settings)
    {
        // 1. Check if our field is present
        if (empty($_POST['yardlii_employer_email'])) {
            return $userdata;
        }

        // 2. Compare Emails
        $currentUser   = wp_get_current_user();
        $employerEmail = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));

        if (!empty($currentUser->user_email) && strcasecmp($employerEmail, $currentUser->user_email) === 0) {
            $message = __('You cannot verify yourself. Please enter a valid employer email.', 'yardlii-core');
            
            // 3. THE KILL SWITCH: Send JSON error and exit script immediately.
            // This prevents WPUF from proceeding to wp_update_user().
            wp_send_json_error([
                'success' => false,
                'error'   => $message
            ]);
            // wp_send_json_error() calls die(), so code execution stops here.
        }

        return $userdata;
    }

    /**
     * Existing submission handler
     */
    public function handle(int $user_id, $form_id): void
    {
        $context = [
            'provider' => 'wpuf',
            'event'    => current_filter(),
        ];

        if (!empty($_POST['yardlii_employer_email'])) {
            $context['employer_email'] = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));
        }

        if (!empty($_POST['first_name'])) {
            $context['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
        }

        if (!empty($_POST['last_name'])) {
            $context['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
        }

        Guards::maybeCreateRequest(
            $user_id,
            (string) $form_id,
            $context
        );
    }
}