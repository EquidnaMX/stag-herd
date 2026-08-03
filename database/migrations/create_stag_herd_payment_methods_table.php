<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stag_herd_payment_methods', function (Blueprint $table): void {
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

            $table->unsignedBigInteger('provider_event_created_at')->default(0)->index();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->unique(
                ['provider', 'credential_context', 'provider_payment_method_id'],
                'stag_herd_payment_methods_provider_pm_unique'
            );

            $table->index(
                ['provider', 'credential_context', 'owner_reference'],
                'stag_herd_payment_methods_owner_index'
            );

            $table->index(
                ['provider', 'credential_context', 'provider_customer_id'],
                'stag_herd_payment_methods_customer_index'
            );

            $table->index(
                ['provider', 'credential_context', 'fingerprint'],
                'stag_herd_payment_methods_fingerprint_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stag_herd_payment_methods');
    }
};
