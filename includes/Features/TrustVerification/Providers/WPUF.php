<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;
use WP_Error;

final class WPUF implements ProviderInterface {
    
    public function getName(): string { 
        return 'wpuf'; 
    }

    public function registerHooks(): void {
        // Process submission (Success)
        add_action('wpuf_update_profile', [$this, 'handle'], 10, 2);
        add_action('wpuf_after_register', [$this, 'handle'], 10, 2);

        // Validate Post Forms (Returns Array)
        add_filter('wpuf_form_validation_error', [$this, 'validateSubmission'], 10, 3);

        // Validate User Registration (Returns WP_Error)
        add_filter('wpuf_process_registration_errors', [$this, 'validateUserSubmission'], 10, 3);

        // Validate User Profile Update (Returns WP_Error)
        add_filter('wpuf_user_profile_update_errors', [$this, 'validateUserSubmission'], 10, 3);
    }

    /**
     * Stops USER form submission (Registration/Profile) on self-vouch.
     *
     * @param WP_Error     $errors
     * @param int|string   $form_id
     * @param array<mixed> $settings
     * @return WP_Error
     */
    public function validateUserSubmission(WP_Error $errors, $form_id, array $settings): WP_Error {
        if (empty($_POST['yardlii_employer_email'])) {
            return $errors;
        }

        $employer_email = sanitize_email($_POST['yardlii_employer_email']);
        $applicant_email = '';

        // 1. Logged-in User (Profile Update)
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $applicant_email = $u->user_email;
        } 
        // 2. New Registration (Email in POST)
        elseif (isset($_POST['user_email'])) {
            $applicant_email = sanitize_email($_POST['user_email']);
        }

        // Check for match
        if ($applicant_email && strcasecmp($applicant_email, $employer_email) === 0) {
            $errors->add(
                'self_vouch_error', 
                __('You cannot use your own email address for employer verification. Please provide a different email.', 'yardlii-core')
            );
        }

        return $errors;
    }

    /**
     * Stops POST form submission on self-vouch.
     *
     * @param array<mixed> $errors
     * @param array<mixed> $config
     * @param int|string   $form_id
     * @return array<mixed>
     */
    public function validateSubmission(array $errors, array $config, $form_id): array {
        if (empty($_POST['yardlii_employer_email'])) {
            return $errors;
        }

        $employer_email = sanitize_email($_POST['yardlii_employer_email']);
        $applicant_email = '';

        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $applicant_email = $u->user_email;
        } elseif (isset($_POST['user_email'])) {
            $applicant_email = sanitize_email($_POST['user_email']);
        }

        if ($applicant_email && strcasecmp($applicant_email, $employer_email) === 0) {
            $errors[] = __('You cannot use your own email address for employer verification. Please provide a different email.', 'yardlii-core');
        }

        return $errors;
    }

    public function handle(int $user_id, $form_id): void {
        $context = [
            'provider' => 'wpuf',
            'event'    => current_filter(),
        ];

        if (!empty($_POST['yardlii_employer_email'])) {
            $submitted_email = sanitize_email($_POST['yardlii_employer_email']);
            
            // Failsafe: Double-check even if validation passed
            $applicant = get_userdata($user_id);
            
            if ($applicant && strcasecmp($applicant->user_email, $submitted_email) !== 0) {
                $context['employer_email'] = $submitted_email;
            }
        }

        if (!empty($_POST['first_name'])) {
            $context['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        if (!empty($_POST['last_name'])) {
            $context['last_name'] = sanitize_text_field($_POST['last_name']);
        }

        \Yardlii\Core\Features\TrustVerification\Requests\Guards::maybeCreateRequest(
            $user_id, 
            (string) $form_id, 
            $context 
        );
    }
}