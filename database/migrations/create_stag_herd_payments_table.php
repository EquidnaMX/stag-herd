<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stag_herd_payments', function (Blueprint $table) {
            $table->id();

            $table->string('provider')->index();
            $table->string('method');

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('MXN');

            $table->string('status')->index();
            $table->string('provider_status')->nullable();

            $table->string('payer_reference')->nullable()->index();
            $table->string('payer_email')->nullable();

            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_order_id')->nullable()->index();

            $table->json('metadata')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['provider', 'provider_payment_id']);
            $table->index(['provider', 'provider_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stag_herd_payments');
    }
};
