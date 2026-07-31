<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Application\Actions\CancelPayment;
use Equidna\StagHerd\Application\Actions\ConfirmPayment;
use Equidna\StagHerd\Application\Actions\CreatePayment;
use Equidna\StagHerd\Application\Actions\LookupPayment;
use Equidna\StagHerd\Application\Actions\RefundPayment;
use Equidna\StagHerd\Application\Actions\SyncPayment;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class PaymentService
{
    public function __construct(
        private CreatePayment $createPayment,
        private ConfirmPayment $confirmPayment,
        private LookupPayment $lookupPayment,
        private CancelPayment $cancelPayment,
        private RefundPayment $refundPayment,
        private SyncPayment $syncPayment,
        private ProviderRegistry $providers,
        private CredentialContextManager $credentials,
    ) {
        //
    }

    public function createPayment(PaymentRequestData $request): Payment
    {
        $provider = $request->provider
            ?? $this->providers->resolveProviderNameForMethod($request->method);

        return $this->credentials->run(
            $provider,
            $request->credentialContext,
            fn (): Payment => $this->createPayment->handle($request->withProvider($provider)),
        );
    }

    public function confirmPayment(PaymentConfirmationData $request): Payment
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): Payment => $this->confirmPayment->handle($request),
        );
    }

    public function lookupPayment(PaymentLookupData $request): Payment
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): Payment => $this->lookupPayment->handle($request),
        );
    }

    public function cancelPayment(PaymentCancellationData $request): Payment
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): Payment => $this->cancelPayment->handle($request),
        );
    }

    public function refundPayment(RefundRequestData $request): Payment
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): Payment => $this->refundPayment->handle($request),
        );
    }

    public function syncPayment(
        PaymentLookupData $lookup,
        PaymentRequestData $fallbackRequest,
    ): Payment {
        return $this->credentials->run(
            $lookup->provider,
            $lookup->credentialContext,
            fn (): Payment => $this->syncPayment->handle(
                lookup: $lookup,
                fallbackRequest: $fallbackRequest,
            ),
        );
    }
}
