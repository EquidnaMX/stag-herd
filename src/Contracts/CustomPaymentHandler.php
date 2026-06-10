<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;

interface CustomPaymentHandler
{
    public function getMethod(): string;

    public function createPayment(PaymentRequestData $request): PaymentResultData;

    public function lookupPayment(PaymentLookupData $request): PaymentResultData;

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData;

    public function refundPayment(RefundRequestData $request): PaymentResultData;
}
