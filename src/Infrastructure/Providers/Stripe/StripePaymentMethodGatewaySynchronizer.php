<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Application\PaymentMethodGatewaySynchronizer;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\PaymentMethodData;

final readonly class StripePaymentMethodGatewaySynchronizer implements PaymentMethodGatewaySynchronizer
{
    public function __construct(
        private StripeGateway $gateway,
    ) {
        //
    }

    public function syncDefaultPaymentMethod(
        PaymentMethodData $paymentMethod,
        ?string $providerPaymentMethodId,
    ): void {
        $this->gateway->updateCustomer(
            customerId: $paymentMethod->providerCustomerId,
            payload: [
                'invoice_settings' => [
                    'default_payment_method' => $providerPaymentMethodId,
                ],
            ],
        );
    }

    public function detachPaymentMethod(PaymentMethodData $paymentMethod): void
    {
        $this->gateway->detachPaymentMethod(
            $paymentMethod->providerPaymentMethodId,
        );
    }
}
