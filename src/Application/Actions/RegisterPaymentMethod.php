<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;

final readonly class RegisterPaymentMethod
{
    public function __construct(
        private ManagesPaymentMethods $paymentMethods,
    ) {
        //
    }

    public function handle(
        PaymentMethodRegisterData $request
    ): PaymentMethodData {
        return $this->paymentMethods->registerPaymentMethod($request);
    }
}
