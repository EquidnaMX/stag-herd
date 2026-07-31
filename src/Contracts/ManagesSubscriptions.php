<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\SubscriptionCancellationData;
use Equidna\StagHerd\Data\SubscriptionData;
use Equidna\StagHerd\Data\SubscriptionLookupData;

interface ManagesSubscriptions
{
    public function lookupSubscription(SubscriptionLookupData $request): SubscriptionData;

    public function cancelSubscription(SubscriptionCancellationData $request): SubscriptionData;
}
