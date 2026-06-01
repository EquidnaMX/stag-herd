<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stag_herd_payments', function (Blueprint $table) {
            $table->id();

            $table->string('provider');
            $table->string('method');

            // Amount in minor units. Example: 12000 = $120.00 MXN.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('MXN');

            $table->string('status');
            $table->string('provider_status')->nullable();

            $table->string('external_reference')->nullable()->index();
            $table->string('payer_reference')->nullable()->index();
            $table->string('payer_email')->nullable();

            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('provider_refund_id')->nullable()->index();

            $table->json('metadata')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stag_herd_payments');
    }
};
