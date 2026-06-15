<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class CreatePayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(PaymentRequestData $request): Payment
    {
        $providerName = $request->provider
            ? strtolower($request->provider)
            : $this->providers->resolveProviderNameForMethod($request->method);

        $request = new PaymentRequestData(
            amount: $request->amount,
            currency: strtoupper($request->currency),
            method: strtolower($request->method),
            provider: $providerName,
            providerOrderId: $request->providerOrderId,
            externalReference: $request->externalReference,
            payerReference: $request->payerReference,
            payerEmail: $request->payerEmail,
            description: $request->description,
            returnUrl: $request->returnUrl,
            cancelUrl: $request->cancelUrl,
            metadata: $request->metadata,
        );

        $provider = $this->providers->get($request->provider);

        $result = $provider->createPayment($request);

        $result->assertMatchesRequest(
            request: $request,
            requireAmount: false,
        );

        $payment = $this->payments->storeFromResult(
            request: $request,
            result: $result,
        );

        PaymentEventDispatcher::dispatchForPayment($payment);

        return $payment;
    }
}
