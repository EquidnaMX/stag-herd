<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\BillingPortalRequestData;
use Equidna\StagHerd\Data\BillingPortalSessionData;

interface CreatesCustomerPortal
{
    public function createBillingPortal(BillingPortalRequestData $request): BillingPortalSessionData;
}
