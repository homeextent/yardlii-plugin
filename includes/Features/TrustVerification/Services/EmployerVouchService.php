<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Services;

use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

final class EmployerVouchService
{
    private const META_TOKEN = '_vp_vouch_token';
    private const META_EMPLOYER_EMAIL = '_vp_employer_email';
    private const ACTION_VERIFY = 'yardlii_tv_employer_verify';

    public function __construct(private Mailer $mailer) {}

    /**
     * Generates a secure token and sends the vouch email.
     */
    public function initiateVouch(int $requestId, string $employerEmail): bool
    {
        // 1. Generate & Store Token
        $token = wp_generate_password(32, false);
        $hash = wp_hash($token . $requestId); // Simple hash for validation
        
        update_post_meta($requestId, self::META_TOKEN, $hash);
        update_post_meta($requestId, self::META_EMPLOYER_EMAIL, sanitize_email($employerEmail));
        update_post_meta($requestId, '_vp_verification_type', 'employer_vouch');

        // 2. Build Link
        $link = admin_url('admin-post.php');
        $link = add_query_arg([
            'action' => self::ACTION_VERIFY,
            'req'    => $requestId,
            'token'  => $token, // Send the raw token; we verify the hash
        ], $link);

        // 3. Send Email
        // We use a generic subject/body here, but ideally, this should be pulled from a config
        $subject = sprintf('Verification Request for Request #%d', $requestId);
        $body = sprintf(
            '<p>Please verify this employee by clicking here: <a href="%s">Verify Employee</a></p>', 
            esc_url($link)
        );

        return $this->mailer->send($employerEmail, $subject, $body, [
            'request_id' => $requestId,
            'verification_link' => $link // Pass to context for placeholders
        ]);
    }

    /**
     * Validates the token for a given request.
     */
    public function validateToken(int $requestId, string $token): bool
    {
        $storedHash = get_post_meta($requestId, self::META_TOKEN, true);
        if (!$storedHash) return false;

        return hash_equals($storedHash, wp_hash($token . $requestId));
    }
}