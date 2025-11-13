<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;

final class WPUF implements ProviderInterface {
    
    public function getName(): string { 
        return 'wpuf'; 
    }

    public function registerHooks(): void {
        // Process submission (Success)
        add_action('wpuf_update_profile', [$this, 'handle'], 10, 2);
        add_action('wpuf_after_register', [$this, 'handle'], 10, 2);

        // Validate submission (Prevent Errors)
        add_filter('wpuf_form_validation_error', [$this, 'validateSubmission'], 10, 3);
    }

    /**
     * Stops form submission if the employer email matches the applicant's email.
     */
    public function validateSubmission($errors, $config, $form_id) {
        // Check if the specific field exists in this submission
        if (empty($_POST['yardlii_employer_email'])) {
            return $errors;
        }

        $employer_email = sanitize_email($_POST['yardlii_employer_email']);
        $applicant_email = '';

        // Scenario A: Logged-in User (Profile Edit)
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $applicant_email = $u->user_email;
        } 
        // Scenario B: New User (Registration) - email is in $_POST
        elseif (isset($_POST['user_email'])) {
            $applicant_email = sanitize_email($_POST['user_email']);
        }

        // Compare emails (Case-insensitive)
        if ($applicant_email && strcasecmp($applicant_email, $employer_email) === 0) {
            // Add error message to WPUF error stack
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