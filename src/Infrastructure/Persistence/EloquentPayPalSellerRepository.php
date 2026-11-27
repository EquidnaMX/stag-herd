<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\PayPalSellerRepository;
use Equidna\StagHerd\Data\PayPalSellerData;
use Equidna\StagHerd\Data\PayPalSellerOnboardingData;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPayPalSeller;
use Illuminate\Support\Facades\DB;

final class EloquentPayPalSellerRepository implements PayPalSellerRepository
{
    public function saveOnboardingResult(PayPalSellerOnboardingData $data): PayPalSellerData
    {
        return DB::transaction(function () use ($data): PayPalSellerData {
            $identity = [
                'seller_merchant_id' => $data->sellerMerchantId,
            ];

            $seller = StagHerdPayPalSeller::query()->updateOrCreate($identity, [
                'tracking_id' => $data->trackingId,
                'owner_reference' => $data->ownerReference,
                'account_status' => $data->accountStatus,
                'consent_status' => $data->consentStatus,
                'permissions' => $data->permissions,
                'capabilities' => $data->capabilities,
                'integration' => $data->integration,
                'payload' => $data->raw,
            ]);

            return $this->toData($seller);
        });
    }

    public function findByTrackingId(string $trackingId): ?PayPalSellerData
    {
        $seller = StagHerdPayPalSeller::query()
            ->where('tracking_id', $trackingId)
            ->first();

        return $seller instanceof StagHerdPayPalSeller
            ? $this->toData($seller)
            : null;
    }

    public function findSellerMerchantIdForOwner(string $ownerReference): ?string
    {
        $sellerMerchantId = StagHerdPayPalSeller::query()
            ->where('owner_reference', $ownerReference)
            ->value('seller_merchant_id');

        return is_string($sellerMerchantId) && $sellerMerchantId !== ''
            ? $sellerMerchantId
            : null;
    }

    private function toData(StagHerdPayPalSeller $seller): PayPalSellerData
    {
        return new PayPalSellerData(
            sellerMerchantId: (string) $seller->seller_merchant_id,
            trackingId: $seller->tracking_id,
            ownerReference: $seller->owner_reference,
            accountStatus: $seller->account_status,
            consentStatus: $seller->consent_status,
            permissions: is_array($seller->permissions) ? $seller->permissions : [],
            capabilities: is_array($seller->capabilities) ? $seller->capabilities : [],
            raw: is_array($seller->payload) ? $seller->payload : [],
        );
    }
}
