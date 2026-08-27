<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Contracts\CredentialResolver;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Support\CredentialContextManager;
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

final class NullCredentialResolver implements CredentialResolver
{
    public function resolve(string $provider, string $credentialContext): array
    {
        return [];
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
            static fn (array $record): bool => ($record['status'] ?? 'active') === 'active',
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
            static fn (array $record): bool => ($record['provider'] ?? null) === $provider
                && ($record['credential_context'] ?? null) === $credentialContext
                && ($record['owner_reference'] ?? null) === $ownerReference,
        ));
    }

    private function key(string $provider, string $credentialContext, string $providerPaymentMethodId): string
    {
        return $provider . '|' . $credentialContext . '|' . $providerPaymentMethodId;
    }
}

final class NullStripeGateway implements StripeGateway
{
    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCheckoutSession(string $checkoutSessionId): array
    {
        return [];
    }

    public function createProduct(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createPrice(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getSubscription(string $subscriptionId): array
    {
        return [];
    }

    public function updateSubscription(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function cancelSubscription(string $subscriptionId, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createBillingPortalSession(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return [];
    }

    public function confirmPaymentIntent(string $paymentIntentId, array $payload = [], ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        return [];
    }

    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCustomer(string $customerId): array
    {
        return [];
    }

    public function createSetupIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getSetupIntent(string $setupIntentId): array
    {
        return [];
    }

    public function getPaymentMethod(string $paymentMethodId): array
    {
        return [];
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return [];
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        return [];
    }

    public function listCustomerPaymentMethods(string $customerId, string $type = 'card'): array
    {
        return [];
    }
}

final class NullPayPalGateway implements PayPalGateway
{
    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getOrder(
        string $orderId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getCapture(
        string $captureId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getSubscription(
        string $subscriptionId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getPaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function deletePaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createPartnerReferral(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getMerchantIntegration(
        string $partnerMerchantId,
        string $sellerMerchantId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function verifyWebhookSignature(
        array $payload,
        ?PayPalRequestContextData $context = null,
    ): bool {
        return false;
    }
}

final class NullMercadoPagoGateway implements MercadoPagoGateway
{
    public function createPayment(array $payload, ?string $idempotencyKey = null, ?string $deviceId = null): array
    {
        return [];
    }

    public function getPayment(string $providerPaymentId): array
    {
        return [];
    }

    public function searchPayments(array $filters = []): array
    {
        return [];
    }

    public function cancelPayment(string $providerPaymentId): array
    {
        return [];
    }

    public function refundPayment(string $providerPaymentId, ?int $amount = null, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createPreference(array $payload): array
    {
        return [];
    }

    public function createPreapprovalPlan(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPreapprovalPlan(string $planId): array
    {
        return [];
    }

    public function createPreapproval(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPreapproval(string $subscriptionId): array
    {
        return [];
    }

    public function updatePreapproval(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCustomerCards(string $customerId): array
    {
        return [];
    }

    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        return [];
    }
}
