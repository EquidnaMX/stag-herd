<?php

namespace Equidna\StagHerd\Tests\Fakes\PaymentMethods;

use Equidna\StagHerd\Application\PaymentMethodGatewaySynchronizer;
use Equidna\StagHerd\Data\PaymentMethodData;

final class NullPaymentMethodGatewaySynchronizer implements PaymentMethodGatewaySynchronizer
{
    public function syncDefaultPaymentMethod(
        PaymentMethodData $paymentMethod,
        ?string $providerPaymentMethodId,
    ): void {
        //
    }

    public function detachPaymentMethod(PaymentMethodData $paymentMethod): void
    {
        //
    }
}
