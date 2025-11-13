<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;

final class WPUF implements ProviderInterface {
    
    public function getName(): string { 
        return 'wpuf'; 
    }

    public function registerHooks(): void {
        add_action('wpuf_update_profile', [$this, 'handle'], 10, 2);
        add_action('wpuf_after_register', [$this, 'handle'], 10, 2);
    }

    public function handle(int $user_id, $form_id): void {
        $context = [
            'provider' => 'wpuf',
            'event'    => current_filter(),
        ];

        if (!empty($_POST['yardlii_employer_email'])) {
            $submitted_email = sanitize_email($_POST['yardlii_employer_email']);
            
            // SECURITY: Prevent Self-Vouching
            // Get the applicant's registered email address
            $applicant = get_userdata($user_id);
            
            // Only proceed if the emails do NOT match (case-insensitive)
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