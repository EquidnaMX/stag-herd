<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;

interface ExtractsPaymentMethodFromPayment
{
    public function paymentMethodFromPayment(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): ?PaymentMethodRegisterData;
}
