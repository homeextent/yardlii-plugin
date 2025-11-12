<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Services\EmployerVouchService;
use Yardlii\Core\Features\TrustVerification\TvDecisionService;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

class EmployerVerificationHandler {

    public function register(): void {
        // Verify Hook
        add_action('admin_post_nopriv_yardlii_tv_employer_verify', [$this, 'handleVerify']);
        add_action('admin_post_yardlii_tv_employer_verify', [$this, 'handleVerify']);
        
        // Reject Hook
        add_action('admin_post_nopriv_yardlii_tv_employer_reject', [$this, 'handleReject']);
        add_action('admin_post_yardlii_tv_employer_reject', [$this, 'handleReject']);
    }

    public function handleVerify(): void {
        $this->processDecision('approve');
    }

    public function handleReject(): void {
        $this->processDecision('reject');
    }

    private function processDecision(string $action): void {
        $req = (int) ($_GET['req'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$req || !$token) wp_die('Invalid link.');

        $mailer = new Mailer();
        $vouchService = new EmployerVouchService($mailer);
        $decisionService = new TvDecisionService($mailer);

        // Check Validation Status
        $status = $vouchService->validateToken($req, $token);

        if ($status === 'expired') {
            wp_die(
                '<h1>Link Expired</h1><p>This verification link has expired (limit: 5 days). Please ask the employee to request a new one.</p>', 
                'Link Expired', 
                ['response' => 410]
            );
        }

        if ($status !== 'valid') {
            wp_die('Verification failed. Invalid token.');
        }

        if (get_post_status($req) !== 'vp_pending') {
            wp_die('This request has already been processed.');
        }

        // Execute Decision (User 0 = System)
        $success = $decisionService->applyDecision($req, $action, ['actor_id' => 0]);

        if ($success) {
            $email = get_post_meta($req, '_vp_employer_email', true);
            
            // Log specific event based on action
            $logKey = ($action === 'approve') ? 'employer_verified' : 'employer_rejected';
            \Yardlii\Core\Features\TrustVerification\Support\Meta::appendLog(
                $req, 
                $logKey, 
                0, 
                ['employer' => $email]
            );

            if ($action === 'approve') {
                wp_die('<h1>Verified!</h1><p>Thank you. The employee has been approved.</p>', 'Verification Success');
            } else {
                wp_die('<h1>Thank You</h1><p>You have indicated you do not know this applicant. We have marked the request as rejected.</p>', 'Submission Received');
            }
        }

        wp_die('System error processing request.');
    }
}