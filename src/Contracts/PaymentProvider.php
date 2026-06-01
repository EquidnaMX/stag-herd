<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;

interface PaymentProvider
{
    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Create a payment and return a normalized result.
     */
    public function createPayment(PaymentRequestData $request): PaymentResultData;
}
