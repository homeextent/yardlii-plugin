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

        // Validation Hooks (New)
        
        // Scenario B: Registration (WP_Error is valid here)
        add_filter('wpuf_process_registration_errors', [$this, 'validateRegistration'], 10, 3);
        
        // Scenario A: Profile Update (Must use Action + JSON response to avoid fatal error)
        add_action('wpuf_before_user_update', [$this, 'validateProfileUpdate'], 10, 3);
    }

    /**
     * Scenario B: Registration Validation
     * Compares employer email to the submitted registration email.
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

        // Retrieve Applicant's email from POST (Registration scenario)
        $applicantEmail = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        $employerEmail  = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));

        if ($applicantEmail && strcasecmp($employerEmail, $applicantEmail) === 0) {
            $errors->add('self_vouch', __('You cannot verify yourself. Please enter a valid employer email.', 'yardlii-core'));
        }

        return $errors;
    }

    /**
     * Scenario A: Profile Update Validation
     * Compares employer email to the currently logged-in user's email.
     *
     * NOTE: We use wp_send_json_error() and exit because this runs inside WPUF's
     * Ajax handler. Returning a WP_Error here would cause a type mismatch crash.
     *
     * @param int                  $user_id
     * @param int                  $form_id
     * @param array<string, mixed> $form_settings
     * @return void
     */
    public function validateProfileUpdate($user_id, $form_id, $form_settings)
    {
        if (empty($_POST['yardlii_employer_email'])) {
            return;
        }

        // Retrieve Applicant's email from Current User (Logged-in scenario)
        $currentUser   = wp_get_current_user();
        $employerEmail = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));

        // wp_get_current_user() always returns an object. Check user_email directly.
        if (!empty($currentUser->user_email) && strcasecmp($employerEmail, $currentUser->user_email) === 0) {
            $message = __('You cannot verify yourself. Please enter a valid employer email.', 'yardlii-core');
            
            // Send JSON error compatible with WPUF frontend and stop execution
            wp_send_json_error([
                'success' => false,
                'error'   => $message
            ]);
        }
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

        // Check if the employer email field exists in the submission
        if (!empty($_POST['yardlii_employer_email'])) {
            $context['employer_email'] = sanitize_email(wp_unslash($_POST['yardlii_employer_email']));
        }

        if (!empty($_POST['first_name']))