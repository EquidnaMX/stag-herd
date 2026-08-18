<?php

namespace Equidna\StagHerd\Infrastructure\Providers;

use Equidna\StagHerd\Contracts\ConfirmsPayments;
use Equidna\StagHerd\Contracts\ExtractsPaymentMethodFromPayment;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Support\PaymentMethodHandlerRegistry;

abstract class AbstractPaymentProvider implements PaymentProvider, ConfirmsPayments, ExtractsPaymentMethodFromPayment
{
    public function __construct(
        protected readonly PaymentMethodHandlerRegistry $handlers,
    ) {
        //
    }

    abstract public function getName(): string;

    /**
     * @return list<string>
     */
    abstract public function getMethods(): array;

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        return $this->handlers
            ->get($this->resolveMethod($request->method))
            ->createPayment($request);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        return $this->handlers
            ->get($this->resolveMethod($request->method ?? data_get($request->metadata, 'method')))
            ->confirmPayment($request);
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return $this->handlers
            ->get($this->resolveMethod($request->method))
            ->lookupPayment($request);
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        return $this->handlers
            ->get($this->resolveMethod($request->method ?? data_get($request->metadata, 'method')))
            ->cancelPayment($request);
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        return $this->handlers
            ->get($this->resolveMethod($request->method ?? data_get($request->metadata, 'method')))
            ->refundPayment($request);
    }

    public function paymentMethodFromPayment(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): ?PaymentMethodRegisterData {
        $handler = $this->handlers->get($this->resolveMethod($result->method));

        if (!$handler instanceof ExtractsPaymentMethodFromPayment) {
            return null;
        }

        return $handler->paymentMethodFromPayment($request, $result);
    }

    protected function resolveMethod(?string $method): string
    {
        if ($method === null || trim($method) === '') {
            throw InvalidPaymentPayloadException::missingField('method');
        }

        return strtolower($method);
    }
}
