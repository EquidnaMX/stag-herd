<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Http\Requests\Payments\PayPal\CreatePartnerReferralRequest;
use Equidna\StagHerd\Tests\TestCase;

class PayPalPartnerReferralRequestTest extends TestCase
{
    public function test_it_builds_partner_referral_payload(): void
    {
        $request = CreatePartnerReferralRequest::create(
            uri: '/paypal/onboarding/referral',
            method: 'POST',
            parameters: [
                'tracking_id' => 'TRACK-123',
                'return_url' => 'https://example.com/paypal/return',
                'action_renewal_url' => 'https://example.com/paypal/renew',
            ],
        );

        $payload = $request->payload();

        $this->assertSame('TRACK-123', $payload['tracking_id']);
        $this->assertSame(
            'https://example.com/paypal/return',
            data_get($payload, 'partner_config_override.return_url'),
        );
        $this->assertSame(
            'https://example.com/paypal/renew',
            data_get($payload, 'partner_config_override.action_renewal_url'),
        );
        $this->assertSame('API_INTEGRATION', data_get($payload, 'operations.0.operation'));
        $this->assertSame('PAYPAL', data_get($payload, 'operations.0.api_integration_preference.rest_api_integration.integration_method'));
        $this->assertSame('THIRD_PARTY', data_get($payload, 'operations.0.api_integration_preference.rest_api_integration.integration_type'));
        $this->assertContains('PAYMENT', data_get($payload, 'operations.0.api_integration_preference.rest_api_integration.third_party_details.features'));
        $this->assertContains('REFUND', data_get($payload, 'operations.0.api_integration_preference.rest_api_integration.third_party_details.features'));
        $this->assertContains('PPCP', $payload['products']);
        $this->assertTrue(data_get($payload, 'legal_consents.0.granted'));
    }

    public function test_paypal_context_defaults_to_default_credential_context(): void
    {
        $request = CreatePartnerReferralRequest::create(
            uri: '/paypal/onboarding/referral',
            method: 'POST',
            parameters: [
                'return_url' => 'https://example.com/paypal/return',
            ],
        );

        $context = $request->paypalContext();

        $this->assertSame('default', $context->credentialContext);
    }

    public function test_idempotency_key_can_come_from_paypal_request_id_header(): void
    {
        $request = CreatePartnerReferralRequest::create(
            uri: '/paypal/onboarding/referral',
            method: 'POST',
            parameters: [
                'return_url' => 'https://example.com/paypal/return',
            ],
            server: [
                'HTTP_PAYPAL_REQUEST_ID' => 'REFERRAL-IDEMPOTENCY-123',
            ],
        );

        $this->assertSame('REFERRAL-IDEMPOTENCY-123', $request->idempotencyKey());
    }
}
