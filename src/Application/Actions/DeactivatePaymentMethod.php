<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodDeactivateData;

final readonly class DeactivatePaymentMethod
{
    public function __construct(
        private ManagesPaymentMethods $paymentMethods,
    ) {
        //
    }

    public function handle(
        PaymentMethodDeactivateData $request
    ): PaymentMethodData {
        return $this->paymentMethods->deactivatePaymentMethod($request);
    }
}
