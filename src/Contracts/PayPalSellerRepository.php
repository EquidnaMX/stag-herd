<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PayPalSellerData;
use Equidna\StagHerd\Data\PayPalSellerOnboardingData;

interface PayPalSellerRepository
{
    public function saveOnboardingResult(PayPalSellerOnboardingData $data): PayPalSellerData;

    public function findByTrackingId(string $trackingId): ?PayPalSellerData;

    public function findSellerMerchantIdForOwner(string $ownerReference): ?string;
}
