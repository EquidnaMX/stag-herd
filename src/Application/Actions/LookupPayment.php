<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class LookupPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(PaymentLookupData $lookup): Payment
    {
        return match ($lookup->lookupType()) {
            'payment_id' => $this->lookupByLocalPaymentId($lookup),
            'provider_payment_id' => $this->lookupByProviderPaymentId($lookup),
            'provider_order_id' => $this->lookupByProviderOrderId($lookup),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                "Unsupported lookup type [{$lookup->lookupType()}]."
            ),
        };
    }

    private function lookupByLocalPaymentId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->find($lookup->paymentId);

        if (! $payment) {
            throw PaymentNotFoundException::withId($lookup->paymentId);
        }

        $providerPaymentId = $payment->references?->providerPaymentId;
        $providerOrderId = $payment->references?->providerOrderId;

        if (! $providerPaymentId && ! $providerOrderId) {
            return $payment;
        }

        $provider = $this->providers->get($payment->provider);
        $method = $this->resolveLookupMethod($lookup, $payment);

        $providerLookup = $providerPaymentId
            ? new PaymentLookupData(
                provider: $payment->provider,
                method: $method,
                providerPaymentId: $providerPaymentId,
            )
            : new PaymentLookupData(
                provider: $payment->provider,
                method: $method,
                providerOrderId: $providerOrderId,
            );

        $result = $provider->lookupPayment($providerLookup);

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function lookupByProviderPaymentId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->findByProviderPaymentId(
            provider: $lookup->provider,
            providerPaymentId: (string) $lookup->providerPaymentId,
        );

        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment(
            new PaymentLookupData(
                provider: $lookup->provider,
                method: $this->resolveLookupMethod($lookup, $payment),
                providerPaymentId: $lookup->providerPaymentId,
            )
        );

        $providerPaymentId = $result->references?->providerPaymentId
            ?? $lookup->providerPaymentId;
        $providerOrderId = $result->references?->providerOrderId;

        if (! $providerPaymentId) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $lookup->lookupValue(),
            );
        }

        $payment ??= $this->findByProviderReferences(
            provider: $lookup->provider,
            providerPaymentId: $providerPaymentId,
            providerOrderId: $providerOrderId,
        );

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId ?? $providerOrderId,
            );
        }

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function lookupByProviderOrderId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->findByProviderOrderId(
            provider: $lookup->provider,
            providerOrderId: (string) $lookup->providerOrderId,
        );

        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment(
            new PaymentLookupData(
                provider: $lookup->provider,
                method: $this->resolveLookupMethod($lookup, $payment),
                providerOrderId: $lookup->providerOrderId,
            )
        );

        $providerPaymentId = $result->references?->providerPaymentId;
        $providerOrderId = $result->references?->providerOrderId
            ?? $lookup->providerOrderId;

        $payment ??= $this->findByProviderReferences(
            provider: $lookup->provider,
            providerPaymentId: $providerPaymentId,
            providerOrderId: $providerOrderId,
        );

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId ?? $providerOrderId ?? $lookup->lookupValue(),
            );
        }

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function applyProviderResult(
        Payment $payment,
        PaymentResultData $result,
        bool $requireAmount,
    ): Payment {
        $result->assertMatchesPayment(
            payment: $payment,
            requireAmount: $requireAmount,
        );

        PaymentStateMachine::assertCanApplyProviderResult(
            payment: $payment,
            providerResultStatus: $result->status,
        );

        $updatedPayment = $this->payments->updateFromResult(
            payment: $payment,
            result: $result,
        );

        PaymentEventDispatcher::dispatchForPayment(
            payment: $updatedPayment,
            previousPayment: $payment,
        );

        return $updatedPayment;
    }

    private function resolveLookupMethod(PaymentLookupData $lookup, ?Payment $payment = null): string
    {
        $resolvedMethod = $this->normalizeMethod($payment?->method)
            ?? $this->normalizeMethod($lookup->method);

        if ($resolvedMethod !== null) {
            return $resolvedMethod;
        }

        $enabledMethods = $this->providers->methodsForProvider($lookup->provider);

        if (count($enabledMethods) === 1) {
            return $enabledMethods[0];
        }

        if (count($enabledMethods) > 1) {
            throw InvalidPaymentPayloadException::invalidField(
                'method',
                sprintf(
                    'Lookup payment method is required for provider [%s] because multiple methods are enabled: [%s].',
                    $lookup->provider,
                    implode(', ', $enabledMethods),
                ),
            );
        }

        $declaredMethods = array_values(array_unique(array_map(
            static fn(string $method): string => strtolower($method),
            $this->providers->get($lookup->provider)->getMethods(),
        )));

        if (count($declaredMethods) === 1) {
            return $declaredMethods[0];
        }

        if (count($declaredMethods) > 1) {
            throw InvalidPaymentPayloadException::invalidField(
                'method',
                sprintf(
                    'Lookup payment method is required for provider [%s] because multiple methods are declared: [%s].',
                    $lookup->provider,
                    implode(', ', $declaredMethods),
                ),
            );
        }

        throw InvalidPaymentPayloadException::missingField('method');
    }

    private function findByProviderReferences(
        string $provider,
        ?string $providerPaymentId = null,
        ?string $providerOrderId = null,
    ): ?Payment {
        if ($providerPaymentId) {
            $payment = $this->payments->findByProviderPaymentId(
                provider: $provider,
                providerPaymentId: $providerPaymentId,
            );

            if ($payment) {
                return $payment;
            }
        }

        if ($providerOrderId) {
            return $this->payments->findByProviderOrderId(
                provider: $provider,
                providerOrderId: $providerOrderId,
            );
        }

        return null;
    }

    private function normalizeMethod(?string $method): ?string
    {
        if ($method === null || trim($method) === '') {
            return null;
        }

        return strtolower($method);
    }
}
