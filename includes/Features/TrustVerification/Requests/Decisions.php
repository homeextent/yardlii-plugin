<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

// NEW: Import the service
use Yardlii\Core\Features\TrustVerification\TvDecisionService;
use Yardlii\Core\Features\TrustVerification\Caps;

/**
 * Handles single-request actions from the Requests table:
 * approve | reject | reopen | resend
 *
 * This class is a thin controller that validates the request
 * and passes all business logic to TvDecisionService.
 */
final class Decisions
{
    // NEW: Service property
    private TvDecisionService $decisionService;

    // NEW: Constructor to inject the service
    public function __construct(TvDecisionService $decisionService)
    {
        $this->decisionService = $decisionService;
    }
    
    /** Wire up admin_action hooks */
    public function register(): void
    {
        add_action('admin_action_yardlii_tv_approve', [$this, 'approve']);
        add_action('admin_action_yardlii_tv_reject', [$this, 'reject']);
        add_action('admin_action_yardlii_tv_reopen', [$this, 'reopen']);
        add_action('admin_action_yardlii_tv_resend', [$this, 'resend']);
    }

    /** Approve a single request and redirect back */
    public function approve(): void { $this->handleDecision('approve'); }

    /** Reject a single request and redirect back */
    public function reject(): void { $this->handleDecision('reject'); }

    /** Reopen a single request and redirect back */
    public function reopen(): void { $this->handleDecision('reopen'); }

    /** Resend decision email (approved/rejected) and redirect back */
    public function resend(): void { $this->handleDecision('resend'); }

    /** Common controller for the 4 admin_action endpoints. */
    private function handleDecision(string $action): void
    {
        if (!current_user_can(Caps::MANAGE)) { //
            wp_die(__('Insufficient permissions.', 'yardlii-core')); //
        }

        check_admin_referer('yardlii_tv_action_nonce'); //

        $request_id = isset($_GET['post']) ? (int) $_GET['post'] : 0; //
        if (!$request_id) {
            wp_die(__('Invalid request.', 'yardlii-core')); //
        }

        // Check for "Send me a copy" checkbox
        $sendCopy = !empty($_REQUEST['tv_send_copy']); //

        // -----------------------------------------------------------------
        // REFACTORED: All logic is now in the service
        // -----------------------------------------------------------------
        // The original applyDecision() method [cite: 1099] is GONE.
        // We now call the service we injected in the constructor.
        
        $ok = $this->decisionService->applyDecision(
            $request_id,
            $action,
            ['cc_self' => $sendCopy] //
        );
        // -----------------------------------------------------------------

        // Redirect back with a notice
        $ref = wp_get_referer(); //
        $ref = $ref ? remove_query_arg(['action', 'action2', '_wpnonce', 'post', 'tv_notice', 'tv_count'], $ref) //
            : admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification'); //

        $ref = add_query_arg([ //
            'tvsection' => 'requests',
            'tv_notice' => $ok ? $action : 'noop',
        ], $ref);

        wp_safe_redirect($ref); //
        exit; //
    }

    //
    // ALL other methods (applyDecision, sendDecisionEmail, loadConfigForForm)
    // are now REMOVED from this file. They live in TvDecisionService.
    //
}