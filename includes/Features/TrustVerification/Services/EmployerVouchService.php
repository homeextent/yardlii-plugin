<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Services;

use Yardlii\Core\Features\TrustVerification\Emails\Mailer;

final class EmployerVouchService
{
    private const META_TOKEN = '_vp_vouch_token';
    private const META_TIMESTAMP = '_vp_vouch_timestamp';
    private const META_EMPLOYER_EMAIL = '_vp_employer_email';
    
    private const ACTION_VERIFY = 'yardlii_tv_employer_verify';
    private const ACTION_REJECT = 'yardlii_tv_employer_reject';

    // 5 Days in seconds
    private const EXPIRY_SECONDS = 432000; 

    public function __construct(private Mailer $mailer) {}

    /**
     * Generates a secure token and sends the vouch email.
     */
    public function initiateVouch(int $requestId, string $employerEmail, string $fName = '', string $lName = ''): bool
    {
        $token = wp_generate_password(32, false);
        $hash = wp_hash($token . $requestId); 
        
        update_post_meta($requestId, self::META_TOKEN, $hash);
        update_post_meta($requestId, self::META_TIMESTAMP, time());
        update_post_meta($requestId, self::META_EMPLOYER_EMAIL, sanitize_email($employerEmail));
        update_post_meta($requestId, '_vp_verification_type', 'employer_vouch');

        $baseLink = admin_url('admin-post.php');
        
        $verifyLink = add_query_arg([
            'action' => self::ACTION_VERIFY,
            'req'    => $requestId,
            'token'  => $token, 
        ], $baseLink);

        $rejectLink = add_query_arg([
            'action' => self::ACTION_REJECT,
            'req'    => $requestId,
            'token'  => $token, 
        ], $baseLink);

        $employeeName = trim("$fName $lName");
        if (!$employeeName) {
            $employeeName = 'An applicant'; 
        }

        $subject = sprintf('Quick check: Does %s work for you?', $employeeName);
        
        $btnStyleYes = 'background-color:#28a745;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;margin-right:15px;';
        $btnStyleNo  = 'background-color:#dc3545;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;';

        $body = sprintf(
            '<p>Hi %s,</p>
            <p><strong>%s</strong> has requested access to Yardlii.com, the exclusive marketplace for contractors.</p>
            <p>To keep our network secure, we only allow verified tradespeople. They listed you as their employer.</p>
            <p><strong>Do they work for you?</strong></p>
            <p style="margin: 25px 0;">
                <a href="%s" style="%s">[ YES - Verify Them ]</a>
                <a href="%s" style="%s">[ NO - I don\'t know them ]</a>
            </p>
            <p><em>Note: This link expires in 5 days.</em></p>
            <p>Thanks,<br>The Yardlii.com Team</p>',
            esc_html($employerEmail), 
            esc_html($employeeName),
            esc_url($verifyLink), $btnStyleYes,
            esc_url($rejectLink), $btnStyleNo
        );

        return $this->mailer->send($employerEmail, $subject, $body, [
            'request_id' => $requestId,
        ]);
    }

    public function validateToken(int $requestId, string $token): string
    {
        $storedHash = get_post_meta($requestId, self::META_TOKEN, true);
        if (!$storedHash) return 'invalid';

        if (!hash_equals($storedHash, wp_hash($token . $requestId))) {
            return 'invalid';
        }

        $generatedAt = (int) get_post_meta($requestId, self::META_TIMESTAMP, true);
        if (time() - $generatedAt > self::EXPIRY_SECONDS) {
            return 'expired';
        }

        return 'valid';
    }
}