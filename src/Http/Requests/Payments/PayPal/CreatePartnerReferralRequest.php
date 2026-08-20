<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Data\PayPalRequestContextData;

class CreatePartnerReferralRequest extends PayPalFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tracking_id' => ['nullable', 'string', 'max:127'],
            'return_url' => ['required', 'url'],
            'action_renewal_url' => ['nullable', 'url'],
            'credential_context' => ['nullable', 'string', 'max:120'],
            'platform_attribution_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $returnUrl = (string) $this->input('return_url');
        $trackingId = $this->input('tracking_id');

        return [
            'tracking_id' => $trackingId ?: (string) str()->uuid(),

            'partner_config_override' => array_filter([
                'return_url' => $returnUrl,
                'return_url_description' => 'Return to application after connecting PayPal.',
                'action_renewal_url' => $this->input('action_renewal_url'),
            ]),

            'operations' => [
                [
                    'operation' => 'API_INTEGRATION',
                    'api_integration_preference' => [
                        'rest_api_integration' => [
                            'integration_method' => 'PAYPAL',
                            'integration_type' => 'THIRD_PARTY',
                            'third_party_details' => [
                                'features' => [
                                    'PAYMENT',
                                    'REFUND',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            'products' => [
                'PPCP',
            ],

            'legal_consents' => [
                [
                    'type' => 'SHARE_DATA_CONSENT',
                    'granted' => true,
                ],
            ],
        ];
    }

    public function idempotencyKey(): ?string
    {
        return $this->headers->get('PayPal-Request-Id')
            ?: $this->input('idempotency_key');
    }

    public function paypalContext(): PayPalRequestContextData
    {
        return new PayPalRequestContextData(
            credentialContext: $this->input('credential_context') ?: 'default',
            platformAttributionId: $this->input('platform_attribution_id'),
        );
    }
}
