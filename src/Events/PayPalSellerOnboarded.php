<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Data\PayPalSellerData;
use Equidna\StagHerd\Data\PayPalSellerOnboardingData;

final readonly class PayPalSellerOnboarded
{
    public function __construct(
        public PayPalSellerData $seller,
        public PayPalSellerOnboardingData $onboarding,
    ) {}
}
