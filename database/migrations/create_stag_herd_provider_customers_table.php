<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stag_herd_provider_customers', function (Blueprint $table): void {
            $table->id();

            $table->string('provider', 32);
            $table->string('credential_context', 128)->default('default');
            $table->string('owner_reference', 191);

            $table->string('provider_customer_id', 255);

            $table->string('email', 191)->nullable();
            $table->string('name', 191)->nullable();

            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('provider_event_created_at')
                ->default(0)
                ->index();

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->unique(
                ['provider', 'credential_context', 'owner_reference'],
                'stag_herd_provider_customers_owner_unique'
            );

            $table->unique(
                ['provider', 'credential_context', 'provider_customer_id'],
                'stag_herd_provider_customers_provider_customer_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stag_herd_provider_customers');
    }
};