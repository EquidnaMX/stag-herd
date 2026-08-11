<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;

final readonly class SetDefaultPaymentMethod
{
    public function __construct(
        private ManagesPaymentMethods $paymentMethods,
    ) {
        //
    }

    public function handle(
        PaymentMethodSetDefaultData $request
    ): PaymentMethodData {
        return $this->paymentMethods->setDefaultPaymentMethod($request);
    }
}
