<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentResultData;

interface ConfirmsPayments
{
    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData;
}
