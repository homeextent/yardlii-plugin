<?php
declare(strict_types=1);

namespace Yardlii\Core\Tests\Integration\Services;

use WP_UnitTestCase;
use Yardlii\Core\Features\TrustVerification\Emails\Mailer;
use Yardlii\Core\Features\TrustVerification\Services\EmployerVouchService;

class EmployerVouchServiceTest extends WP_UnitTestCase
{
    private EmployerVouchService $service;
    private int $requestId;

    public function setUp(): void
    {
        parent::setUp();
        $mailer = new Mailer();
        $this->service = new EmployerVouchService($mailer);
        
        // Create a dummy request post
        $this->requestId = self::factory()->post->create([
            'post_type' => 'verification_request',
            'post_status' => 'vp_pending'
        ]);
    }

    public function test_initiateVouch_stores_meta_and_sends_email()
    {
        $employerEmail = 'boss@example.com';
        
        // Mock Mailer would be ideal, but for integration we check the meta
        $result = $this->service->initiateVouch($this->requestId, $employerEmail);
        
        $this->assertTrue($result, 'initiateVouch should return true on success');
        
        // Check meta
        $hash = get_post_meta($this->requestId, '_vp_vouch_token', true);
        $email = get_post_meta($this->requestId, '_vp_employer_email', true);
        $type = get_post_meta($this->requestId, '_vp_verification_type', true);
        
        $this->assertNotEmpty($hash, 'Token hash should be stored');
        $this->assertEquals($employerEmail, $email, 'Employer email should be stored');
        $this->assertEquals('employer_vouch', $type, 'Verification type should be set');
    }

    public function test_validateToken_success()
    {
        // Manually seed a token
        $rawToken = 'secret123';
        $hash = wp_hash($rawToken . $this->requestId);
        update_post_meta($this->requestId, '_vp_vouch_token', $hash);

        $this->assertTrue(
            $this->service->validateToken($this->requestId, $rawToken),
            'Valid token should pass validation'
        );
    }

    public function test_validateToken_failure()
    {
        // Seed a token
        $rawToken = 'secret123';
        $hash = wp_hash($rawToken . $this->requestId);
        update_post_meta($this->requestId, '_vp_vouch_token', $hash);

        $this->assertFalse(
            $this->service->validateToken($this->requestId, 'wrong_token'),
            'Invalid token should fail validation'
        );
    }
}