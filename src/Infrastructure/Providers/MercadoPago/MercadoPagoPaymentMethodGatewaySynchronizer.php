<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Application\PaymentMethodGatewaySynchronizer;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\PaymentMethodData;

final readonly class MercadoPagoPaymentMethodGatewaySynchronizer implements PaymentMethodGatewaySynchronizer
{
    public function __construct(
        private MercadoPagoGateway $gateway,
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
        $this->gateway->deleteCustomerCard(
            $paymentMethod->providerCustomerId,
            $paymentMethod->providerPaymentMethodId,
        );
    }
}
