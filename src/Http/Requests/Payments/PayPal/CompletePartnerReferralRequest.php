<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Data\PayPalRequestContextData;

class CompletePartnerReferralRequest extends PayPalFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'seller_merchant_id' => ['nullable', 'string', 'max:120'],
            'merchant_id' => ['nullable', 'string', 'max:120'],
            'merchantId' => ['nullable', 'string', 'max:120'],

            'partner_merchant_id' => ['nullable', 'string', 'max:120'],
            'tracking_id' => ['nullable', 'string', 'max:127'],
            'owner_reference' => ['nullable', 'string', 'max:120'],

            'credential_context' => ['nullable', 'string', 'max:120'],
            'platform_attribution_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function sellerMerchantId(): string
    {
        return (string) (
            $this->input('seller_merchant_id')
            ?: $this->input('merchant_id')
            ?: $this->input('merchantId')
        );
    }

    public function partnerMerchantId(): ?string
    {
        $value = $this->input('partner_merchant_id');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function trackingId(): ?string
    {
        $value = $this->input('tracking_id');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function ownerReference(): ?string
    {
        $value = $this->input('owner_reference');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function paypalContext(): PayPalRequestContextData
    {
        return new PayPalRequestContextData(
            credentialContext: $this->input('credential_context') ?: 'default',
            platformAttributionId: $this->input('platform_attribution_id'),
        );
    }
}
