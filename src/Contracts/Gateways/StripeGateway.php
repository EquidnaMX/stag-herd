<?php

namespace Equidna\StagHerd\Contracts;

interface StripeGateway
{
    public function getPaymentDetails(string $paymentId): object;

    public function getRefund(string $paymentId): object;
}
