<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\CheckoutLookupData;
use Equidna\StagHerd\Data\CheckoutRequestData;
use Equidna\StagHerd\Data\CheckoutSessionData;

interface HostedCheckoutProvider
{
    public function createCheckout(CheckoutRequestData $request): CheckoutSessionData;

    public function lookupCheckout(CheckoutLookupData $request): CheckoutSessionData;
}
