<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Data\PaymentMethodData;

interface PaymentMethodGatewaySynchronizer
{
    public function syncDefaultPaymentMethod(
        PaymentMethodData $paymentMethod,
        ?string $providerPaymentMethodId,
    ): void;

    public function detachPaymentMethod(PaymentMethodData $paymentMethod): void;
}
