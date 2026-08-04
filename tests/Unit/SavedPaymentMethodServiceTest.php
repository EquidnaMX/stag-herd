<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\SavedPaymentMethodService;
use Equidna\StagHerd\Contracts\CredentialResolver;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\SavedPaymentMethodLookupData;
use Equidna\StagHerd\Data\SavedPaymentMethodUpsertData;
use Equidna\StagHerd\Infrastructure\Persistence\EloquentPaymentMethodRepository;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;

final class SavedPaymentMethodServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('testing');

        Schema::connection('testing')->create('stag_herd_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('credential_context', 128)->default('default');
            $table->string('owner_reference', 191);
            $table->string('provider_customer_id', 255);
            $table->string('provider_payment_method_id', 255);
            $table->string('type', 32)->default('card');
            $table->string('fingerprint', 255)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->string('brand', 30)->nullable();
            $table->char('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 32)->default('active');
            $table->timestamp('attached_at')->nullable();
            $table->timestamp('detached_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('provider_event_created_at')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'credential_context', 'provider_payment_method_id']);
        });
    }

    public function test_it_upserts_and_lists_active_methods_for_an_owner(): void
    {
        $service = $this->makeService();

        $first = $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
            brand: 'visa',
            last4: '4242',
            expMonth: 10,
            expYear: 2030,
        ));

        $second = $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_456',
            brand: 'mastercard',
            last4: '4444',
            expMonth: 1,
            expYear: 2032,
        ));

        $active = $service->listActive(new SavedPaymentMethodLookupData(
            provider: 'stripe',
            ownerReference: 'client-1',
        ));

        $this->assertSame('pm_123', $first->providerPaymentMethodId);
        $this->assertTrue($first->isDefault);
        $this->assertSame('pm_456', $second->providerPaymentMethodId);
        $this->assertFalse($second->isDefault);
        $this->assertCount(2, $active);
        $this->assertSame('pm_123', $active[0]->providerPaymentMethodId);
        $this->assertSame('pm_456', $active[1]->providerPaymentMethodId);
    }

    public function test_it_marks_one_method_as_default(): void
    {
        $service = $this->makeService();

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
        ));

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_456',
        ));

        $default = $service->markDefault(new SavedPaymentMethodLookupData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerPaymentMethodId: 'pm_456',
        ));

        $this->assertSame('pm_456', $default->providerPaymentMethodId);
        $this->assertTrue($default->isDefault);
        $this->assertSame(1, DB::table('stag_herd_payment_methods')->where('provider_payment_method_id', 'pm_456')->value('is_default'));
        $this->assertSame(0, DB::table('stag_herd_payment_methods')->where('provider_payment_method_id', 'pm_123')->value('is_default'));
    }

    public function test_it_deactivates_a_default_method_and_promotes_the_next_one(): void
    {
        /** @var StripeGateway&MockInterface $gateway */
        $gateway = Mockery::mock(StripeGateway::class);

        /** @var Expectation $defaultExpectation */
        $defaultExpectation = $gateway->expects('updateCustomer');
        $defaultExpectation->once()
            ->with('cus_123', [
                'invoice_settings' => [
                    'default_payment_method' => 'pm_456',
                ],
            ])
            ->andReturn(['id' => 'cus_123']);

        /** @var Expectation $detachExpectation */
        $detachExpectation = $gateway->expects('detachPaymentMethod');
        $detachExpectation->once()
            ->with('pm_123')
            ->andReturn(['id' => 'pm_123']);

        $service = $this->makeService($gateway);

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
        ));

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_456',
        ));

        $detached = $service->deactivate(new SavedPaymentMethodLookupData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerPaymentMethodId: 'pm_123',
        ));

        $active = $service->listActive(new SavedPaymentMethodLookupData(
            provider: 'stripe',
            ownerReference: 'client-1',
        ));

        $this->assertSame('detached', $detached->status);
        $this->assertFalse($detached->isDefault);
        $this->assertCount(1, $active);
        $this->assertSame('pm_456', $active[0]->providerPaymentMethodId);
        $this->assertTrue($active[0]->isDefault);
    }

    public function test_it_resolves_a_usable_method_and_updates_last_used_at(): void
    {
        $service = $this->makeService();

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
        ));

        $resolved = $service->resolveUsable(new SavedPaymentMethodLookupData(
            provider: 'stripe',
            ownerReference: 'client-1',
        ));

        $this->assertSame('pm_123', $resolved->providerPaymentMethodId);
        $this->assertNotNull(DB::table('stag_herd_payment_methods')->where('provider_payment_method_id', 'pm_123')->value('last_used_at'));
    }

    public function test_it_ignores_an_older_provider_event_when_upserting(): void
    {
        $service = $this->makeService();

        $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
            brand: 'visa',
            providerEventCreatedAt: 200,
        ));

        $saved = $service->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: 'client-1',
            providerCustomerId: 'cus_123',
            providerPaymentMethodId: 'pm_123',
            brand: 'mastercard',
            providerEventCreatedAt: 100,
        ));

        $this->assertSame('visa', $saved->brand);
        $this->assertSame(200, $saved->providerEventCreatedAt);
    }

    private function makeService(?StripeGateway $gateway = null): SavedPaymentMethodService
    {
        $gateway ??= Mockery::mock(StripeGateway::class);

        return new SavedPaymentMethodService(
            paymentMethods: new EloquentPaymentMethodRepository(),
            credentials: new CredentialContextManager(new class () implements CredentialResolver {
                public function resolve(string $provider, string $credentialContext): array
                {
                    return [];
                }
            }),
            stripeGateway: $gateway,
        );
    }
}
