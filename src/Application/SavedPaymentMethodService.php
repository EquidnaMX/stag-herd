<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\ManagesSavedPaymentMethods;
use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Data\SavedPaymentMethodData;
use Equidna\StagHerd\Data\SavedPaymentMethodLookupData;
use Equidna\StagHerd\Data\SavedPaymentMethodUpsertData;
use Equidna\StagHerd\Exceptions\SavedPaymentMethodNotFoundException;
use Equidna\StagHerd\Support\CredentialContextManager;

final readonly class SavedPaymentMethodService implements ManagesSavedPaymentMethods
{
    public function __construct(
        private PaymentMethodRepository $paymentMethods,
        private CredentialContextManager $credentials,
        private StripeGateway $stripeGateway,
    ) {
        //
    }

    public function upsert(SavedPaymentMethodUpsertData $request): SavedPaymentMethodData
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): SavedPaymentMethodData => $this->upsertWithinContext($request),
        );
    }

    public function listActive(SavedPaymentMethodLookupData $request): array
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): array => array_map(
                static fn(array $record): SavedPaymentMethodData => SavedPaymentMethodData::fromArray($record),
                $this->paymentMethods->listActiveByOwner(
                    strtolower($request->provider),
                    $request->credentialContext,
                    $request->ownerReference,
                ),
            ),
        );
    }

    public function markDefault(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): SavedPaymentMethodData => $this->markDefaultWithinContext($request),
        );
    }

    public function deactivate(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): SavedPaymentMethodData => $this->deactivateWithinContext($request),
        );
    }

    public function resolveUsable(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn(): SavedPaymentMethodData => $this->resolveUsableWithinContext($request),
        );
    }

    private function upsertWithinContext(SavedPaymentMethodUpsertData $request): SavedPaymentMethodData
    {
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

        $savedMethod = $this->requireStoredMethod(
            provider: $request->provider,
            credentialContext: $request->credentialContext,
            ownerReference: $request->ownerReference,
            providerPaymentMethodId: $request->providerPaymentMethodId,
        );

        if ($savedMethod->status !== 'active') {
            return $savedMethod;
        }

        $shouldBecomeDefault = $request->isDefault
            || $this->paymentMethods->findDefaultByOwner(
                strtolower($request->provider),
                $request->credentialContext,
                $request->ownerReference,
            ) === null;

        if (! $shouldBecomeDefault) {
            return $savedMethod;
        }

        return $this->markDefaultWithinContext(new SavedPaymentMethodLookupData(
            provider: $request->provider,
            ownerReference: $request->ownerReference,
            credentialContext: $request->credentialContext,
            providerPaymentMethodId: $request->providerPaymentMethodId,
        ));
    }

    private function markDefaultWithinContext(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        $savedMethod = $this->resolveExistingActiveMethod($request);

        $this->syncProviderDefaultPaymentMethod(
            provider: $savedMethod->provider,
            providerCustomerId: $savedMethod->providerCustomerId,
            providerPaymentMethodId: $savedMethod->providerPaymentMethodId,
        );

        $this->paymentMethods->markAsDefault(
            $savedMethod->provider,
            $savedMethod->credentialContext,
            $savedMethod->ownerReference,
            $savedMethod->providerPaymentMethodId,
        );

        return $this->requireStoredMethod(
            provider: $savedMethod->provider,
            credentialContext: $savedMethod->credentialContext,
            ownerReference: $savedMethod->ownerReference,
            providerPaymentMethodId: $savedMethod->providerPaymentMethodId,
        );
    }

    private function deactivateWithinContext(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        $savedMethod = $this->resolveExistingActiveMethod($request);

        $nextDefault = null;

        foreach ($this->listActiveByOwner($savedMethod) as $candidate) {
            if ($candidate->providerPaymentMethodId === $savedMethod->providerPaymentMethodId) {
                continue;
            }

            $nextDefault = $candidate;

            break;
        }

        $this->syncProviderDefaultPaymentMethod(
            provider: $savedMethod->provider,
            providerCustomerId: $savedMethod->providerCustomerId,
            providerPaymentMethodId: $nextDefault?->providerPaymentMethodId,
        );

        $this->detachProviderPaymentMethod($savedMethod);

        $this->paymentMethods->markDetached(
            $savedMethod->provider,
            $savedMethod->credentialContext,
            $savedMethod->providerPaymentMethodId,
        );

        if ($nextDefault instanceof SavedPaymentMethodData) {
            $this->paymentMethods->markAsDefault(
                $nextDefault->provider,
                $nextDefault->credentialContext,
                $nextDefault->ownerReference,
                $nextDefault->providerPaymentMethodId,
            );
        }

        return SavedPaymentMethodData::fromArray(
            $this->paymentMethods->findByProviderPaymentMethodId(
                $savedMethod->provider,
                $savedMethod->credentialContext,
                $savedMethod->providerPaymentMethodId,
            ) ?? [],
        );
    }

    private function resolveUsableWithinContext(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
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
            throw SavedPaymentMethodNotFoundException::forOwner(
                strtolower($request->provider),
                $request->ownerReference,
                $request->providerPaymentMethodId,
            );
        }

        $savedMethod = SavedPaymentMethodData::fromArray($record);

        $this->paymentMethods->touchLastUsed(
            $savedMethod->provider,
            $savedMethod->credentialContext,
            $savedMethod->providerPaymentMethodId,
        );

        return $this->requireStoredMethod(
            provider: $savedMethod->provider,
            credentialContext: $savedMethod->credentialContext,
            ownerReference: $savedMethod->ownerReference,
            providerPaymentMethodId: $savedMethod->providerPaymentMethodId,
        );
    }

    private function resolveExistingActiveMethod(SavedPaymentMethodLookupData $request): SavedPaymentMethodData
    {
        $providerPaymentMethodId = $request->requireProviderPaymentMethodId();

        $record = $this->paymentMethods->findActiveByOwner(
            strtolower($request->provider),
            $request->credentialContext,
            $request->ownerReference,
            $providerPaymentMethodId,
        );

        if (! is_array($record)) {
            throw SavedPaymentMethodNotFoundException::forOwner(
                strtolower($request->provider),
                $request->ownerReference,
                $providerPaymentMethodId,
            );
        }

        return SavedPaymentMethodData::fromArray($record);
    }

    private function requireStoredMethod(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): SavedPaymentMethodData {
        $record = $this->paymentMethods->findByProviderPaymentMethodId(
            strtolower($provider),
            $credentialContext,
            $providerPaymentMethodId,
        );

        if (! is_array($record) || (string) ($record['owner_reference'] ?? '') !== $ownerReference) {
            throw SavedPaymentMethodNotFoundException::forOwner(
                strtolower($provider),
                $ownerReference,
                $providerPaymentMethodId,
            );
        }

        return SavedPaymentMethodData::fromArray($record);
    }

    /**
     * @return array<int, SavedPaymentMethodData>
     */
    private function listActiveByOwner(SavedPaymentMethodData $savedMethod): array
    {
        return array_map(
            static fn(array $record): SavedPaymentMethodData => SavedPaymentMethodData::fromArray($record),
            $this->paymentMethods->listActiveByOwner(
                $savedMethod->provider,
                $savedMethod->credentialContext,
                $savedMethod->ownerReference,
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

    private function detachProviderPaymentMethod(SavedPaymentMethodData $savedMethod): void
    {
        if ($savedMethod->provider !== 'stripe') {
            return;
        }

        $this->stripeGateway->detachPaymentMethod(
            $savedMethod->providerPaymentMethodId,
        );
    }
}
