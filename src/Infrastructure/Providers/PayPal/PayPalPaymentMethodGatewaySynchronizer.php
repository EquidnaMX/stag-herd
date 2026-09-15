<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Application\PaymentMethodGatewaySynchronizer;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PaymentMethodData;

final readonly class PayPalPaymentMethodGatewaySynchronizer implements PaymentMethodGatewaySynchronizer
{
    public function __construct(
        private PayPalGateway $gateway,
    ) {
        //
    }

    public function syncDefaultPaymentMethod(
        PaymentMethodData $paymentMethod,
        ?string $providerPaymentMethodId,
    ): void {
        //
    }

    public function detachPaymentMethod(PaymentMethodData $paymentMethod): void
    {
        $this->gateway->deletePaymentToken(
            $paymentMethod->providerPaymentMethodId,
        );
    }
}
