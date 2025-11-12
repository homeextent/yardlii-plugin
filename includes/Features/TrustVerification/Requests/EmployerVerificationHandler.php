<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Services\EmployerVouchService;
use Yardlii\Core\Features\TrustVerification\TvDecisionService;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

class EmployerVerificationHandler {

    public function register(): void {
        add_action('admin_post_nopriv_yardlii_tv_employer_verify', [$this, 'handleVerify']);
        add_action('admin_post_yardlii_tv_employer_verify', [$this, 'handleVerify']);
    }

    public function handleVerify(): void {
        $req = (int) ($_GET['req'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$req || !$token) {
            wp_die('Invalid link.');
        }

        // Boot services manually since this is a simplified handler
        $mailer = new Mailer();
        $vouchService = new EmployerVouchService($mailer);
        $decisionService = new TvDecisionService($mailer);

        if ($vouchService->validateToken($req, $token)) {
            
            // Verify current status is pending
            if (get_post_status($req) !== 'vp_pending') {
                wp_die('This request has already been processed.');
            }

            // Execute Approval as "User 0" (System/Guest)
            $success = $decisionService->applyDecision($req, 'approve', [
                'actor_id' => 0 
            ]);

            if ($success) {
                // Retrieve employer email for logging context
                $email = get_post_meta($req, '_vp_employer_email', true);
                \Yardlii\Core\Features\TrustVerification\Support\Meta::appendLog(
                    $req, 
                    'employer_verified', 
                    0, 
                    ['employer' => $email]
                );

                // Redirect to success page
                wp_die('<h1>Verified!</h1><p>Thank you. The employee has been approved.</p>', 'Verification Success');
            }
        }

        wp_die('Verification failed or token expired.');
    }
}