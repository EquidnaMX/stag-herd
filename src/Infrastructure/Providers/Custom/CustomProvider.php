<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Custom;

use Equidna\StagHerd\Contracts\ConfirmsPayments;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;

final class CustomProvider implements PaymentProvider, ConfirmsPayments
{
    public function __construct(
        private readonly CustomPaymentHandlerRegistry $handlers,
    ) {
        //
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $method = $this->resolveMethod($request->method);

        return $this->handlers
            ->get($method)
            ->createPayment($request);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        $method = $this->resolveMethod(
            $request->method ?? data_get($request->metadata, 'method')
        );

        return $this->handlers
            ->get($method)
            ->confirmPayment($request);
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        $method = $this->resolveMethod($request->method);

        return $this->handlers
            ->get($method)
            ->lookupPayment($request);
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        $method = $this->resolveMethod(
            $request->method ?? data_get($request->metadata, 'method')
        );

        return $this->handlers
            ->get($method)
            ->cancelPayment($request);
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        $method = $this->resolveMethod(
            $request->method ?? data_get($request->metadata, 'method')
        );

        return $this->handlers
            ->get($method)
            ->refundPayment($request);
    }

    private function resolveMethod(?string $method): string
    {
        if ($method === null || trim($method) === '') {
            throw InvalidPaymentPayloadException::missingField('method');
        }

        return strtolower($method);
    }
}
