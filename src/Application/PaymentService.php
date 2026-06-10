<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Application\Actions\CancelPayment;
use Equidna\StagHerd\Application\Actions\CreatePayment;
use Equidna\StagHerd\Application\Actions\LookupPayment;
use Equidna\StagHerd\Application\Actions\RefundPayment;
use Equidna\StagHerd\Application\Actions\SyncPayment;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Payment;

final readonly class PaymentService
{
    public function __construct(
        private CreatePayment $createPayment,
        private LookupPayment $lookupPayment,
        private CancelPayment $cancelPayment,
        private RefundPayment $refundPayment,
        private SyncPayment $syncPayment,
    ) {
        //
    }

    public function createPayment(PaymentRequestData $request): Payment
    {
        return $this->createPayment->handle($request);
    }

    public function lookupPayment(PaymentLookupData $request): Payment
    {
        return $this->lookupPayment->handle($request);
    }

    public function cancelPayment(PaymentCancellationData $request): Payment
    {
        return $this->cancelPayment->handle($request);
    }

    public function refundPayment(RefundRequestData $request): Payment
    {
        return $this->refundPayment->handle($request);
    }

    public function syncPayment(
        PaymentLookupData $lookup,
        PaymentRequestData $fallbackRequest,
    ): Payment {
        return $this->syncPayment->handle(
            lookup: $lookup,
            fallbackRequest: $fallbackRequest,
        );
    }
}
