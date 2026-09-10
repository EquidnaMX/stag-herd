<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\Fakes\Credentials\NullCredentialResolver;
use Equidna\StagHerd\Tests\Fakes\Gateways\NullMercadoPagoGateway;
use Equidna\StagHerd\Tests\Fakes\Gateways\NullStripeGateway;
use Equidna\StagHerd\Tests\Fakes\Gateways\NullPayPalGateway;
use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Tests\TestCase;

final class PaymentMethodServiceTest extends TestCase
{
    public function test_register_payment_method_returns_existing_active_method_for_same_owner_fingerprint(): void
    {
        $repository = new InMemoryPaymentMethodRepository();
        $service = $this->service($repository);

        $first = $service->registerPaymentMethod(new PaymentMethodRegisterData(
            provider: 'stripe',
            ownerReference: 'WP-25454484',
            providerCustomerId: 'cus_first',
            providerPaymentMethodId: 'pm_first',
            fingerprint: 'modmChWtgTXsjmPQ',
            last4: '4242',
        ));

        $second = $service->registerPaymentMethod(new PaymentMethodRegisterData(
            provider: 'stripe',
            ownerReference: 'WP-25454484',
            providerCustomerId: 'cus_second',
            providerPaymentMethodId: 'pm_second',
            fingerprint: 'modmChWtgTXsjmPQ',
            displayName: 'Tarjeta personal',
            last4: '4242',
        ));

        $this->assertSame('pm_first', $first->providerPaymentMethodId);
        $this->assertSame('pm_first', $second->providerPaymentMethodId);
        $this->assertSame('Tarjeta personal', $second->displayName);
        $this->assertSame(1, $repository->count());
        $this->assertSame(1, $repository->touchCount('pm_first'));
    }

    private function service(InMemoryPaymentMethodRepository $repository): PaymentMethodService
    {
        return new PaymentMethodService(
            paymentMethods: $repository,
            credentials: new CredentialContextManager(new NullCredentialResolver()),
            stripeGateway: new NullStripeGateway(),
            payPalGateway: new NullPayPalGateway(),
            mercadoPagoGateway: new NullMercadoPagoGateway(),
        );
    }
}

final class InMemoryPaymentMethodRepository implements PaymentMethodRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    /** @var array<string, int> */
    private array $touches = [];

    private int $nextId = 1;

    public function upsert(array $attributes): bool
    {
        $key = $this->key(
            (string) $attributes['provider'],
            (string) $attributes['credential_context'],
            (string) $attributes['provider_payment_method_id'],
        );

        $this->records[$key] = array_merge($this->records[$key] ?? ['id' => $this->nextId++], $attributes);

        return true;
    }

    public function findByProviderPaymentMethodId(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): ?array {
        return $this->records[$this->key($provider, $credentialContext, $providerPaymentMethodId)] ?? null;
    }

    public function findByFingerprint(
        string $provider,
        string $credentialContext,
        string $providerCustomerId,
        string $fingerprint,
    ): ?array {
        foreach ($this->records as $record) {
            if (($record['provider'] ?? null) === $provider
                && ($record['credential_context'] ?? null) === $credentialContext
                && ($record['provider_customer_id'] ?? null) === $providerCustomerId
                && ($record['fingerprint'] ?? null) === $fingerprint
                && ($record['status'] ?? 'active') === 'active'
            ) {
                return $record;
            }
        }

        return null;
    }

    public function findActiveByOwnerFingerprint(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $fingerprint,
    ): ?array {
        foreach ($this->records as $record) {
            if (($record['provider'] ?? null) === $provider
                && ($record['credential_context'] ?? null) === $credentialContext
                && ($record['owner_reference'] ?? null) === $ownerReference
                && ($record['fingerprint'] ?? null) === $fingerprint
                && ($record['status'] ?? 'active') === 'active'
            ) {
                return $record;
            }
        }

        return null;
    }

    public function listByOwner(string $provider, string $credentialContext, string $ownerReference): array
    {
        return $this->filterByOwner($provider, $credentialContext, $ownerReference);
    }

    public function listActiveByOwner(string $provider, string $credentialContext, string $ownerReference): array
    {
        return array_values(array_filter(
            $this->filterByOwner($provider, $credentialContext, $ownerReference),
            static fn(array $record): bool => ($record['status'] ?? 'active') === 'active',
        ));
    }

    public function findActiveByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): ?array {
        $record = $this->findByProviderPaymentMethodId($provider, $credentialContext, $providerPaymentMethodId);

        if (
            !is_array($record)
            || ($record['owner_reference'] ?? null) !== $ownerReference
            || ($record['status'] ?? 'active') !== 'active'
        ) {
            return null;
        }

        return $record;
    }

    public function findDefaultByOwner(string $provider, string $credentialContext, string $ownerReference): ?array
    {
        foreach ($this->listActiveByOwner($provider, $credentialContext, $ownerReference) as $record) {
            if ((bool) ($record['is_default'] ?? false)) {
                return $record;
            }
        }

        return null;
    }

    public function markAsDefault(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): void {
        foreach ($this->records as &$record) {
            if (($record['provider'] ?? null) === $provider
                && ($record['credential_context'] ?? null) === $credentialContext
                && ($record['owner_reference'] ?? null) === $ownerReference
            ) {
                $record['is_default'] = ($record['provider_payment_method_id'] ?? null) === $providerPaymentMethodId;
            }
        }
    }

    public function markDetached(string $provider, string $credentialContext, string $providerPaymentMethodId): void
    {
        $key = $this->key($provider, $credentialContext, $providerPaymentMethodId);

        if (isset($this->records[$key])) {
            $this->records[$key]['status'] = 'detached';
            $this->records[$key]['is_default'] = false;
        }
    }

    public function touchLastUsed(string $provider, string $credentialContext, string $providerPaymentMethodId): void
    {
        $this->touches[$providerPaymentMethodId] = ($this->touches[$providerPaymentMethodId] ?? 0) + 1;
    }

    public function updateDisplayName(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
        string $displayName,
    ): void {
        $key = $this->key($provider, $credentialContext, $providerPaymentMethodId);

        if (isset($this->records[$key])) {
            $this->records[$key]['display_name'] = $displayName;
        }
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function touchCount(string $providerPaymentMethodId): int
    {
        return $this->touches[$providerPaymentMethodId] ?? 0;
    }

    /** @return array<int, array<string, mixed>> */
    private function filterByOwner(string $provider, string $credentialContext, string $ownerReference): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(array $record): bool => ($record['provider'] ?? null) === $provider
                && ($record['credential_context'] ?? null) === $credentialContext
                && ($record['owner_reference'] ?? null) === $ownerReference,
        ));
    }

    private function key(string $provider, string $credentialContext, string $providerPaymentMethodId): string
    {
        return $provider . '|' . $credentialContext . '|' . $providerPaymentMethodId;
    }
}
