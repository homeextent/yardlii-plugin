<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Caps;
// Import our new service class
use Yardlii\Core\Features\TrustVerification\Services\TvDecisionService;
// --- ADD THIS LINE ---
use Yardlii\Core\Features\TrustVerification\Requests\CPT;

/**
 * Handles single-request actions from the Requests table:
 * approve | reject | reopen | resend
 *
 * This is a thin CONTROLLER that delegates all logic to TvDecisionService.
 */
final class Decisions
{
    /** Wire up admin_action hooks */
    public function register(): void
    {
        add_action('admin_action_yardlii_tv_approve', [$this, 'approve']);
        add_action('admin_action_yardlii_tv_reject',  [$this, 'reject']);
        add_action('admin_action_yardlii_tv_reopen',  [$this, 'reopen']);
        add_action('admin_action_yardlii_tv_resend',  [$this, 'resend']);
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
        if (! current_user_can(Caps::MANAGE)) {
            wp_die(__('Insufficient permissions.', 'yardlii-core'));
        }
        check_admin_referer('yardlii_tv_action_nonce');

        $request_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$request_id) wp_die(__('Invalid request.', 'yardlii-core'));

        $post = get_post($request_id);
        // This line will now work correctly
        if (! $post || $post->post_type !== CPT::POST_TYPE) {
            wp_die(__('Invalid request.', 'yardlii-core'));
        }

        // Pick up the “Send me a copy” checkbox
        $sendCopy = ! empty($_REQUEST['tv_send_copy']);

        // --- REFACTORED ---
        // Delegate all logic to the new service class
        $ok = (new TvDecisionService())->apply($request_id, $action, ['cc_self' => $sendCopy]);
        // --- END REFACTOR ---

        // Bounce back with a scoped notice
        $ref = wp_get_referer();
        $ref = $ref ? remove_query_arg(['action','action2','_wpnonce','post','tv_notice','tv_count'], $ref)
                    : admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');

        $ref = add_query_arg([
            'tvsection' => 'requests',
            'tv_notice' => $ok ? $action : 'noop',
        ], $ref);

        wp_safe_redirect($ref);
        // --- ADD THIS IGNORE COMMENT ---
        // @phpstan-ignore-next-line
        exit;
    } // <-- This brace closes handleDecision

    /**
     * Public-facing method to apply a decision.
     * This is kept for backward compatibility (e.g., for bulk actions)
     * and delegates all logic to the new service.
     *
     * @param int    $request_id
     * @param string $action   approve|reject|reopen|resend
     * @param array  $opts     ['cc_self' => bool]
     */
    public function applyDecision(int $request_id, string $action, array $opts = []): bool
    {
        // Delegate all logic to the new service class
        return (new TvDecisionService())->apply($request_id, $action, $opts);
    }

} // <-- This brace closes the Decisions class