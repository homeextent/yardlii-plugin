public function handle(int $user_id, $form_id): void {
        $context = [
            'provider' => 'wpuf',
            'event'    => current_filter(),
        ];

        // Capture Employer Email
        if (!empty($_POST['yardlii_employer_email'])) {
            $context['employer_email'] = sanitize_email($_POST['yardlii_employer_email']);
        }

        // NEW: Capture Name for Email Personalization
        // WPUF maps these to standard WP fields usually available in $_POST
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