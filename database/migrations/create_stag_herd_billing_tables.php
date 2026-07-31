<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stag_herd_billing_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('credential_context', 128);
            $table->string('resource_type', 32);
            $table->string('provider_resource_id', 255);
            $table->string('status', 64)->nullable()->index();
            $table->unsignedBigInteger('provider_event_created_at')->default(0)->index();
            $table->json('payload');
            $table->timestamps();
            $table->unique(
                ['provider', 'credential_context', 'resource_type', 'provider_resource_id'],
                'stag_herd_billing_resource_unique',
            );
        });

        Schema::create('stag_herd_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 255)->unique();
            $table->string('provider', 32);
            $table->string('credential_context', 128);
            $table->string('provider_event_id', 255);
            $table->string('event_type', 128);
            $table->string('resource_type', 32);
            $table->string('provider_resource_id', 255)->nullable();
            $table->string('state', 20)->default('processing')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['provider', 'credential_context', 'provider_event_id'],
                'stag_herd_webhook_event_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stag_herd_webhook_events');
        Schema::dropIfExists('stag_herd_billing_resources');
    }
};
