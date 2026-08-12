<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\StoredPaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Support\PaymentEventDispatcher;

final readonly class StorePaymentResult
{
    public function __construct(
        private PaymentRepository $payments,
        private RegisterPaymentMethodFromResult $registerPaymentMethodFromResult,
    ) {
        //
    }

    public function store(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): StoredPaymentResultData {
        $payment = $this->payments->storeFromResult(
            request: $request,
            result: $result,
        );

        $paymentMethod = $this->registerPaymentMethodFromResult->handle(
            request: $request,
            result: $result,
        );

        PaymentEventDispatcher::dispatchForPayment($payment);

        return new StoredPaymentResultData($payment, $paymentMethod);
    }

    public function update(
        Payment $payment,
        PaymentResultData $result,
        ?PaymentRequestData $request = null,
    ): StoredPaymentResultData {
        $updatedPayment = $this->payments->updateFromResult(
            payment: $payment,
            result: $result,
        );

        $paymentMethod = $request instanceof PaymentRequestData
            ? $this->registerPaymentMethodFromResult->handle($request, $result)
            : null;

        PaymentEventDispatcher::dispatchForPayment(
            payment: $updatedPayment,
            previousPayment: $payment,
        );

        return new StoredPaymentResultData($updatedPayment, $paymentMethod);
    }
}
