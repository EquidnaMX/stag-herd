<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Infrastructure\Persistence\EloquentBillingResourceRepository;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EloquentBillingResourceRepositoryTest extends TestCase
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
        Schema::connection('testing')->create('stag_herd_billing_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('credential_context');
            $table->string('resource_type');
            $table->string('provider_resource_id');
            $table->string('status')->nullable();
            $table->unsignedBigInteger('provider_event_created_at')->default(0);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['provider', 'credential_context', 'resource_type', 'provider_resource_id']);
        });
    }

    public function test_it_ignores_an_older_provider_event(): void
    {
        $repository = new EloquentBillingResourceRepository();

        $this->assertTrue($repository->upsert(
            'stripe',
            'method-1',
            'subscription',
            'sub_123',
            'active',
            ['created' => 200],
            200,
        ));
        $this->assertFalse($repository->upsert(
            'stripe',
            'method-1',
            'subscription',
            'sub_123',
            'canceled',
            ['created' => 100],
            100,
        ));

        $this->assertSame('active', DB::table('stag_herd_billing_resources')->value('status'));
        $this->assertSame(200, DB::table('stag_herd_billing_resources')->value('provider_event_created_at'));
    }
}
