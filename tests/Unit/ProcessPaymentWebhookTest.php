<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\Actions\LookupPayment;
use Equidna\StagHerd\Application\Actions\ProcessPaymentWebhook;
use Equidna\StagHerd\Contracts\BillingResourceRepository;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Events\PaymentApproved;
use Equidna\StagHerd\Exceptions\DuplicateWebhookException;
use Equidna\StagHerd\Support\ProviderRegistry;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use RuntimeException;

class ProcessPaymentWebhookTest extends TestCase
{
    public function test_it_marks_a_webhook_as_processed_after_a_successful_claim(): void
    {
        config()->set('stag-herd.providers.mercado_pago.webhooks.parser', FakeWebhookParser::class);

        $repository = new InMemoryWebhookPaymentRepository(new Payment(
            id: 'payment-1',
            provider: 'mercado_pago',
            method: 'card',
            amount: 12000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
            providerStatus: 'pending',
            references: new ProviderReferencesData(providerPaymentId: '123'),
        ));

        $registry = new ProviderRegistry();
        $registry->register(new FakeWebhookPaymentProvider());

        $store = new SpyWebhookIdempotencyStore();

        $action = new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment($registry, $repository),
            idempotency: $store,
        );

        $payment = $action->handle($this->payload());

        $this->assertSame('payment-1', $payment?->id);
        $this->assertSame(PaymentStatusEnum::APPROVED, $payment?->status);
        $this->assertSame([
            'stag-herd:webhooks:mercado_pago:payment.updated:payment:123',
        ], $store->claimedKeys);
        $this->assertSame([
            'stag-herd:webhooks:mercado_pago:payment.updated:payment:123',
        ], $store->processedKeys);
        $this->assertSame([], $store->releasedKeys);
    }

    public function test_it_releases_processing_claim_when_webhook_processing_fails(): void
    {
        config()->set('stag-herd.providers.mercado_pago.webhooks.parser', FakeWebhookParser::class);

        $repository = new InMemoryWebhookPaymentRepository(new Payment(
            id: 'payment-1',
            provider: 'mercado_pago',
            method: 'card',
            amount: 12000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
            providerStatus: 'pending',
            references: new ProviderReferencesData(providerPaymentId: '123'),
        ));

        $registry = new ProviderRegistry();
        $registry->register(new FailingWebhookPaymentProvider());

        $store = new SpyWebhookIdempotencyStore();

        $action = new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment($registry, $repository),
            idempotency: $store,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lookup failed');

        try {
            $action->handle($this->payload());
        } finally {
            $this->assertSame([
                'stag-herd:webhooks:mercado_pago:payment.updated:payment:123',
            ], $store->claimedKeys);
            $this->assertSame([], $store->processedKeys);
            $this->assertSame([
                'stag-herd:webhooks:mercado_pago:payment.updated:payment:123',
            ], $store->releasedKeys);
        }
    }

    public function test_it_throws_duplicate_when_the_claim_is_not_acquired(): void
    {
        config()->set('stag-herd.providers.mercado_pago.webhooks.parser', FakeWebhookParser::class);

        $repository = new InMemoryWebhookPaymentRepository(new Payment(
            id: 'payment-1',
            provider: 'mercado_pago',
            method: 'card',
            amount: 12000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
            providerStatus: 'pending',
            references: new ProviderReferencesData(providerPaymentId: '123'),
        ));

        $registry = new ProviderRegistry();
        $registry->register(new FakeWebhookPaymentProvider());

        $store = new SpyWebhookIdempotencyStore(false);

        $action = new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment($registry, $repository),
            idempotency: $store,
        );

        $this->expectException(DuplicateWebhookException::class);

        try {
            $action->handle($this->payload());
        } finally {
            $this->assertSame([
                'stag-herd:webhooks:mercado_pago:payment.updated:payment:123',
            ], $store->claimedKeys);
            $this->assertSame([], $store->processedKeys);
            $this->assertSame([], $store->releasedKeys);
        }
    }

    public function test_it_falls_back_to_the_provider_single_method_when_lookup_method_is_missing(): void
    {
        config()->set('stag-herd.providers.mercado_pago.webhooks.parser', FakeWebhookParser::class);
        config()->set('stag-herd.providers.mercado_pago.enabled', true);
        config()->set('stag-herd.providers.mercado_pago.methods.card.enabled', true);

        $repository = new InMemoryWebhookPaymentRepository(new Payment(
            id: 'payment-1',
            provider: 'mercado_pago',
            method: 'card',
            amount: 12000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
            providerStatus: 'pending',
            references: new ProviderReferencesData(providerPaymentId: 'provider-123'),
        ));

        $provider = new SpyFallbackWebhookPaymentProvider('provider-123');

        $registry = new ProviderRegistry();
        $registry->register($provider);

        $action = new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment($registry, $repository),
            idempotency: new SpyWebhookIdempotencyStore(),
        );

        $payment = $action->handle($this->payload());

        $this->assertSame('payment-1', $payment?->id);
        $this->assertSame('card', $provider->lastLookupMethod);
    }

    public function test_it_dispatches_a_normalized_approved_event_for_a_billing_payment(): void
    {
        config()->set('stag-herd.providers.stripe.webhooks.parser', FakeBillingPaymentWebhookParser::class);
        Event::fake([PaymentApproved::class]);
        $billingResources = new SpyBillingResourceRepository();
        $repository = new InMemoryWebhookPaymentRepository(new Payment(
            id: 'unused',
            provider: 'stripe',
            method: 'card',
            amount: 1000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
        ));
        $action = new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment(new ProviderRegistry(), $repository),
            idempotency: new SpyWebhookIdempotencyStore(),
            billingResources: $billingResources,
        );

        $payment = $action->handle(new WebhookPayloadData(
            provider: 'stripe',
            payload: ['id' => 'evt_1'],
            headers: [],
            query: [],
            rawBody: '{}',
            ipAddress: '127.0.0.1',
            credentialContext: 'method-uuid',
        ));

        $this->assertSame(PaymentStatusEnum::APPROVED, $payment?->status);
        $this->assertSame('purchase-uuid', $payment?->metadata['purchase_uuid'] ?? null);
        $this->assertSame('payment', $billingResources->resourceType);
        Event::assertDispatched(PaymentApproved::class);
    }

    private function payload(): WebhookPayloadData
    {
        return new WebhookPayloadData(
            provider: 'mercado_pago',
            payload: ['id' => 'wh-1'],
            headers: [],
            query: [],
            rawBody: '{}',
            ipAddress: '127.0.0.1',
        );
    }
}

final class FakeBillingPaymentWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        return new NormalizedWebhookData(
            provider: 'stripe',
            eventType: 'payment_intent.succeeded',
            resourceType: 'payment_intent',
            resourceId: 'pi_123',
            providerPaymentId: 'pi_123',
            method: 'card',
            rawPayload: [
                'created' => 200,
                'data' => ['object' => [
                    'id' => 'pi_123',
                    'amount_received' => 12500,
                    'currency' => 'mxn',
                    'metadata' => [
                        'purchase_uuid' => 'purchase-uuid',
                        'payment_method_uuid' => 'method-uuid',
                    ],
                ]],
            ],
            providerEventId: 'evt_1',
            credentialContext: $webhook->credentialContext,
            status: 'succeeded',
        );
    }
}

final class SpyBillingResourceRepository implements BillingResourceRepository
{
    public ?string $resourceType = null;

    public function upsert(
        string $provider,
        string $credentialContext,
        string $resourceType,
        string $providerResourceId,
        ?string $status,
        array $payload,
        ?int $providerEventCreatedAt = null,
    ): bool {
        $this->resourceType = $resourceType;

        return true;
    }
}

final class FakeWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        return new NormalizedWebhookData(
            provider: 'mercado_pago',
            eventType: 'payment.updated',
            resourceType: 'payment',
            resourceId: '123',
            providerPaymentId: '123',
            rawPayload: $webhook->payload,
        );
    }
}

final class SpyWebhookIdempotencyStore implements WebhookIdempotencyStore
{
    /**
     * @var list<string>
     */
    public array $claimedKeys = [];

    /**
     * @var list<string>
     */
    public array $processedKeys = [];

    /**
     * @var list<string>
     */
    public array $releasedKeys = [];

    /**
     * @var array<string, string>
     */
    private array $states = [];

    public function __construct(
        private readonly bool $claimResult = true,
    ) {
        //
    }

    public function claim(string $key, int $ttlSeconds): bool
    {
        $this->claimedKeys[] = $key;

        if (!$this->claimResult) {
            return false;
        }

        $this->states[$key] = 'processing';

        return true;
    }

    public function markProcessed(string $key, int $ttlSeconds): void
    {
        $this->processedKeys[] = $key;
        $this->states[$key] = 'processed';
    }

    public function releaseIfProcessing(string $key): void
    {
        if (($this->states[$key] ?? null) !== 'processing') {
            return;
        }

        $this->releasedKeys[] = $key;
        unset($this->states[$key]);
    }
}

class FakeWebhookPaymentProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function getMethods(): array
    {
        return ['card'];
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return PaymentResultData::approved(
            provider: 'mercado_pago',
            method: 'card',
            providerStatus: 'approved',
            references: new ProviderReferencesData(providerPaymentId: '123'),
            amount: 12000,
            currency: 'MXN',
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }
}

final class FailingWebhookPaymentProvider extends FakeWebhookPaymentProvider
{
    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        throw new RuntimeException('lookup failed');
    }
}

final class SpyFallbackWebhookPaymentProvider extends FakeWebhookPaymentProvider
{
    public ?string $lastLookupMethod = null;

    public function __construct(
        private readonly string $providerPaymentId,
    ) {
        //
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        $this->lastLookupMethod = $request->method;

        return PaymentResultData::approved(
            provider: 'mercado_pago',
            method: 'card',
            providerStatus: 'approved',
            references: new ProviderReferencesData(providerPaymentId: $this->providerPaymentId),
            amount: 12000,
            currency: 'MXN',
        );
    }
}

final class InMemoryWebhookPaymentRepository implements \Equidna\StagHerd\Contracts\PaymentRepository
{
    public function __construct(
        private Payment $payment,
    ) {
        //
    }

    public function storeFromResult(PaymentRequestData $request, PaymentResultData $result): Payment
    {
        throw new RuntimeException('Not implemented.');
    }

    public function find(int|string $id): ?Payment
    {
        return (string) $this->payment->id === (string) $id ? $this->payment : null;
    }

    public function findByProviderPaymentId(string $provider, string $providerPaymentId): ?Payment
    {
        if (
            $this->payment->provider === $provider
            && $this->payment->references?->providerPaymentId === $providerPaymentId
        ) {
            return $this->payment;
        }

        return null;
    }

    public function findByProviderOrderId(string $provider, string $providerOrderId): ?Payment
    {
        if (
            $this->payment->provider === $provider
            && $this->payment->references?->providerOrderId === $providerOrderId
        ) {
            return $this->payment;
        }

        return null;
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        return $this->payment->externalReference === $externalReference ? $this->payment : null;
    }

    public function updateFromResult(Payment $payment, PaymentResultData $result): Payment
    {
        $this->payment = new Payment(
            id: $payment->id,
            provider: $payment->provider,
            method: $payment->method,
            amount: $result->amount ?? $payment->amount,
            currency: $result->currency ?? $payment->currency,
            status: $result->status,
            providerStatus: $result->providerStatus,
            externalReference: $payment->externalReference,
            payerReference: $payment->payerReference,
            payerEmail: $payment->payerEmail,
            references: $result->references ?? $payment->references,
            metadata: array_merge($payment->metadata, $result->metadata),
        );

        return $this->payment;
    }
}
