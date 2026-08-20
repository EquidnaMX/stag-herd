<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stag_herd_paypal_sellers', function (Blueprint $table): void {
            $table->id();

            $table->string('seller_merchant_id')->unique();
            $table->string('tracking_id')->nullable()->index();
            $table->string('owner_reference')->nullable()->index();

            $table->string('account_status')->nullable();
            $table->string('consent_status')->nullable();

            $table->json('permissions')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('integration')->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stag_herd_paypal_sellers');
    }
};
