<?php

/**
 * Unit tests for WebhookVerifier.
 *
 * Tests webhook signature validation for all payment providers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Unit\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Unit\Support;

use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Http\Request;

class WebhookVerifierTest extends TestCase
{
    public function test_verifies_valid_stripe_signature()
    {
        $payload = '{"event":"test"}';
        $secret = 'whsec_test_secret';
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);

        $result = WebhookVerifier::verifyStripeSignature(
            $request,
            $secret
        );

        $this->assertTrue($result['valid']);
    }

    public function test_rejects_invalid_stripe_signature()
    {
        $payload = '{"event":"test"}';
        $secret = 'whsec_test_secret';
        $timestamp = time();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=invalid_signature",
        ], $payload);

        $result = WebhookVerifier::verifyStripeSignature(
            $request,
            $secret
        );

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('reason', $result);
    }

    public function test_rejects_stripe_signature_with_expired_timestamp()
    {
        $payload = '{"event":"test"}';
        $secret = 'whsec_test_secret';
        $oldTimestamp = time() - 600; // 10 minutes ago
        $signature = hash_hmac('sha256', $oldTimestamp . '.' . $payload, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$oldTimestamp},v1={$signature}",
        ], $payload);

        $result = WebhookVerifier::verifyStripeSignature(
            $request,
            $secret,
            300 // 5 minute tolerance
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tolerance', $result['reason']);
    }

    public function test_checks_idempotency_for_duplicate_events()
    {
        $eventId = 'evt_test_123';

        // First check - should return false (not duplicate)
        $isDuplicate1 = WebhookVerifier::checkIdempotency($eventId);
        $this->assertFalse($isDuplicate1);

        // Second check - should return true (duplicate)
        $isDuplicate2 = WebhookVerifier::checkIdempotency($eventId);
        $this->assertTrue($isDuplicate2);
    }

    public function test_verifies_mercado_pago_signature()
    {
        $payload = '{"id":123,"type":"payment"}';
        $secret = 'test_secret';
        $ts = '1234567890';
        $requestId = 'req-123';

        // Manifest format matches implementation: "id:{$dataId};request-id:{$requestId};ts:{$ts};"
        $manifest = "id:123;request-id:{$requestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $xSignature = "ts={$ts},v1={$hash}";

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => $xSignature,
            'HTTP_X_REQUEST_ID' => $requestId,
        ], $payload);

        $result = WebhookVerifier::verifyMercadoPagoSignature(
            $request,
            $secret
        );

        $this->assertTrue($result['valid']);
    }

    public function test_rejects_invalid_mercado_pago_signature()
    {
        $payload = '{"id":123,"type":"payment"}';
        $secret = 'test_secret';

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => 'invalid_signature',
        ], $payload);

        $result = WebhookVerifier::verifyMercadoPagoSignature(
            $request,
            $secret
        );

        $this->assertFalse($result['valid']);
    }
}
