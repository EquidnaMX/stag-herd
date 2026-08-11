<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodDeactivateData;
use Equidna\StagHerd\Data\PaymentMethodLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;
use Equidna\StagHerd\Data\PaymentMethodsListData;
use Equidna\StagHerd\Exceptions\PaymentMethodNotFoundException;
use Equidna\StagHerd\Support\CredentialContextManager;

final readonly class PaymentMethodService implements ManagesPaymentMethods
{
    public function __construct(
        private PaymentMethodRepository $paymentMethods,
        private CredentialContextManager $credentials,
        private StripeGateway $stripeGateway,
        private PayPalGateway $payPalGateway,
        private MercadoPagoGateway $mercadoPagoGateway,
    ) {
        //
    }

    public function registerPaymentMethod(
        PaymentMethodRegisterData $request
    ): PaymentMethodData {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): PaymentMethodData => $this->upsertWithinContext($request),
        );
    }

    /**
     * @return array<int, PaymentMethodData>
     */
    public function listPaymentMethods(
        PaymentMethodsListData $request
    ): array {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): array => array_map(
                static fn(array $record): PaymentMethodData => PaymentMethodData::fromArray($record),
                $this->paymentMethods->listActiveByOwner(
                    strtolower($request->provider),
                    $request->credentialContext,
                    $request->ownerReference,
                ),
            ),
        );
    }

    public function setDefaultPaymentMethod(
        PaymentMethodSetDefaultData $request
    ): PaymentMethodData {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): PaymentMethodData => $this->markDefaultWithinContext(
                $request->toLookupData()
            ),
        );
    }

    public function deactivatePaymentMethod(
        PaymentMethodDeactivateData $request
    ): PaymentMethodData {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): PaymentMethodData => $this->deactivateWithinContext(
                $request->toLookupData()
            ),
        );
    }

    public function resolveUsablePaymentMethod(
        PaymentMethodLookupData $request
    ): PaymentMethodData {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): PaymentMethodData => $this->resolveUsableWithinContext($request),
        );
    }

    private function upsertWithinContext(
        PaymentMethodRegisterData $request
    ): PaymentMethodData {
        $attributes = $request->toArray();

        if (($attributes['status'] ?? 'active') === 'active' && ($attributes['attached_at'] ?? null) === null) {
            $attributes['attached_at'] = now();
            $attributes['detached_at'] = null;
        }

        if (($attributes['status'] ?? 'active') === 'detached' && ($attributes['detached_at'] ?? null) === null) {
            $attributes['detached_at'] = now();
            $attributes['is_default'] = false;
        }

        $this->paymentMethods->upsert($attributes);

        $paymentMethod = $this->requireStoredMethod(
            provider: $request->provider,
            credentialContext: $request->credentialContext,
            ownerReference: $request->ownerReference,
            providerPaymentMethodId: $request->providerPaymentMethodId,
        );

        if ($paymentMethod->status !== 'active') {
            return $paymentMethod;
        }

        $shouldBecomeDefault = $request->isDefault
            || $this->paymentMethods->findDefaultByOwner(
                strtolower($request->provider),
                $request->credentialContext,
                $request->ownerReference,
            ) === null;

        if (! $shouldBecomeDefault) {
            return $paymentMethod;
        }

        return $this->markDefaultWithinContext(new PaymentMethodLookupData(
            provider: $request->provider,
            ownerReference: $request->ownerReference,
            credentialContext: $request->credentialContext,
            providerPaymentMethodId: $request->providerPaymentMethodId,
        ));
    }

    private function markDefaultWithinContext(
        PaymentMethodLookupData $request
    ): PaymentMethodData {
        $paymentMethod = $this->resolveExistingActiveMethod($request);

        $this->syncProviderDefaultPaymentMethod(
            provider: $paymentMethod->provider,
            providerCustomerId: $paymentMethod->providerCustomerId,
            providerPaymentMethodId: $paymentMethod->providerPaymentMethodId,
        );

        $this->paymentMethods->markAsDefault(
            $paymentMethod->provider,
            $paymentMethod->credentialContext,
            $paymentMethod->ownerReference,
            $paymentMethod->providerPaymentMethodId,
        );

        return $this->requireStoredMethod(
            provider: $paymentMethod->provider,
            credentialContext: $paymentMethod->credentialContext,
            ownerReference: $paymentMethod->ownerReference,
            providerPaymentMethodId: $paymentMethod->providerPaymentMethodId,
        );
    }

    private function deactivateWithinContext(
        PaymentMethodLookupData $request
    ): PaymentMethodData {
        $paymentMethod = $this->resolveExistingActiveMethod($request);

        $nextDefault = null;

        foreach ($this->listActiveByOwner($paymentMethod) as $candidate) {
            if ($candidate->providerPaymentMethodId === $paymentMethod->providerPaymentMethodId) {
                continue;
            }

            $nextDefault = $candidate;

            break;
        }

        $this->syncProviderDefaultPaymentMethod(
            provider: $paymentMethod->provider,
            providerCustomerId: $paymentMethod->providerCustomerId,
            providerPaymentMethodId: $nextDefault?->providerPaymentMethodId,
        );

        $this->detachProviderPaymentMethod($paymentMethod);

        $this->paymentMethods->markDetached(
            $paymentMethod->provider,
            $paymentMethod->credentialContext,
            $paymentMethod->providerPaymentMethodId,
        );

        if ($nextDefault instanceof PaymentMethodData) {
            $this->paymentMethods->markAsDefault(
                $nextDefault->provider,
                $nextDefault->credentialContext,
                $nextDefault->ownerReference,
                $nextDefault->providerPaymentMethodId,
            );
        }

        return PaymentMethodData::fromArray(
            $this->paymentMethods->findByProviderPaymentMethodId(
                $paymentMethod->provider,
                $paymentMethod->credentialContext,
                $paymentMethod->providerPaymentMethodId,
            ) ?? [],
        );
    }

    private function resolveUsableWithinContext(
        PaymentMethodLookupData $request
    ): PaymentMethodData {
        $record = $request->providerPaymentMethodId !== null
            ? $this->paymentMethods->findActiveByOwner(
                strtolower($request->provider),
                $request->credentialContext,
                $request->ownerReference,
                $request->providerPaymentMethodId,
            )
            : ($this->paymentMethods->findDefaultByOwner(
                strtolower($request->provider),
                $request->credentialContext,
                $request->ownerReference,
            ) ?? $this->paymentMethods->listActiveByOwner(
                strtolower($request->provider),
                $request->credentialContext,
                $request->ownerReference,
            )[0] ?? null);

        if (! is_array($record)) {
            throw PaymentMethodNotFoundException::forOwner(
                strtolower($request->provider),
                $request->ownerReference,
                $request->providerPaymentMethodId,
            );
        }

        $paymentMethod = PaymentMethodData::fromArray($record);

        $this->paymentMethods->touchLastUsed(
            $paymentMethod->provider,
            $paymentMethod->credentialContext,
            $paymentMethod->providerPaymentMethodId,
        );

        return $this->requireStoredMethod(
            provider: $paymentMethod->provider,
            credentialContext: $paymentMethod->credentialContext,
            ownerReference: $paymentMethod->ownerReference,
            providerPaymentMethodId: $paymentMethod->providerPaymentMethodId,
        );
    }

    private function resolveExistingActiveMethod(
        PaymentMethodLookupData $request
    ): PaymentMethodData {
        $providerPaymentMethodId = $request->requireProviderPaymentMethodId();

        $record = $this->paymentMethods->findActiveByOwner(
            strtolower($request->provider),
            $request->credentialContext,
            $request->ownerReference,
            $providerPaymentMethodId,
        );

        if (! is_array($record)) {
            throw PaymentMethodNotFoundException::forOwner(
                strtolower($request->provider),
                $request->ownerReference,
                $providerPaymentMethodId,
            );
        }

        return PaymentMethodData::fromArray($record);
    }

    private function requireStoredMethod(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): PaymentMethodData {
        $record = $this->paymentMethods->findByProviderPaymentMethodId(
            strtolower($provider),
            $credentialContext,
            $providerPaymentMethodId,
        );

        if (! is_array($record) || (string) ($record['owner_reference'] ?? '') !== $ownerReference) {
            throw PaymentMethodNotFoundException::forOwner(
                strtolower($provider),
                $ownerReference,
                $providerPaymentMethodId,
            );
        }

        return PaymentMethodData::fromArray($record);
    }

    /**
     * @return array<int, PaymentMethodData>
     */
    private function listActiveByOwner(PaymentMethodData $paymentMethod): array
    {
        return array_map(
            static fn(array $record): PaymentMethodData => PaymentMethodData::fromArray($record),
            $this->paymentMethods->listActiveByOwner(
                $paymentMethod->provider,
                $paymentMethod->credentialContext,
                $paymentMethod->ownerReference,
            ),
        );
    }

    private function syncProviderDefaultPaymentMethod(
        string $provider,
        string $providerCustomerId,
        ?string $providerPaymentMethodId,
    ): void {
        if ($provider !== 'stripe') {
            return;
        }

        $this->stripeGateway->updateCustomer(
            customerId: $providerCustomerId,
            payload: [
                'invoice_settings' => [
                    'default_payment_method' => $providerPaymentMethodId,
                ],
            ],
        );
    }

    private function detachProviderPaymentMethod(
        PaymentMethodData $paymentMethod
    ): void {
        match ($paymentMethod->provider) {
            'stripe' => $this->stripeGateway->detachPaymentMethod(
                $paymentMethod->providerPaymentMethodId,
            ),
            'paypal' => $this->payPalGateway->deletePaymentToken(
                $paymentMethod->providerPaymentMethodId,
            ),
            'mercado_pago' => $this->mercadoPagoGateway->deleteCustomerCard(
                $paymentMethod->providerCustomerId,
                $paymentMethod->providerPaymentMethodId,
            ),
            default => null,
        };
    }
}
