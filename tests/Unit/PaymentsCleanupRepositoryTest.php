<?php

/**
 * Tests for cleanup-oriented repository helpers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Unit
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Unit;

use Carbon\Carbon;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Repositories\EloquentPaymentRepository;
use Equidna\StagHerd\Tests\Fixtures\ValidPayment;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentsCleanupRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stag-herd.payment_model', ValidPayment::class);

        $this->migratePayments();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('payments');

        parent::tearDown();
    }

    public function test_delete_orphans_removes_records(): void
    {
        $repo = new EloquentPaymentRepository();

        $repo->create([
            'id_order' => null,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'orph-1',
            'method_data' => ['foo' => 'bar'],
            'amount' => 10.50,
            'link' => null,
            'email' => 'a@example.com',
            'dt_registration' => now()->subDay(),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $repo->create([
            'id_order' => 20,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'kept-1',
            'method_data' => ['foo' => 'bar'],
            'amount' => 10.50,
            'link' => null,
            'email' => 'a@example.com',
            'dt_registration' => now()->subDay(),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $this->assertSame(1, $repo->deleteOrphans());
        $this->assertSame(1, DB::table('payments')->count());
    }

    public function test_pending_payments_filters_by_range_and_methods(): void
    {
        $repo = new EloquentPaymentRepository();
        $now = Carbon::parse('2024-01-01 00:00:00');

        $repo->create([
            'id_order' => 10,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'recent-paypal',
            'method_data' => ['foo' => 'bar'],
            'amount' => 50.00,
            'link' => null,
            'email' => 'a@example.com',
            'dt_registration' => $now->copy()->subHours(2),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $repo->create([
            'id_order' => 11,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'old-paypal',
            'method_data' => ['foo' => 'bar'],
            'amount' => 60.00,
            'link' => null,
            'email' => 'b@example.com',
            'dt_registration' => $now->copy()->subDays(2),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $repo->create([
            'id_order' => 12,
            'id_client' => 1,
            'method' => 'CLIP',
            'method_id' => 'recent-clip',
            'method_data' => ['foo' => 'bar'],
            'amount' => 20.00,
            'link' => null,
            'email' => 'c@example.com',
            'dt_registration' => $now->copy()->subHours(1),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $since = $now->copy()->subDay();
        $payments = $repo->pendingPayments($since, null, ['PAYPAL']);

        $this->assertCount(1, $payments->all());
    }

    public function test_cancel_pending_before_updates_status(): void
    {
        $repo = new EloquentPaymentRepository();
        $now = Carbon::parse('2024-01-10 00:00:00');

        $recent = $repo->create([
            'id_order' => 10,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'recent',
            'method_data' => ['foo' => 'bar'],
            'amount' => 75.00,
            'link' => null,
            'email' => 'a@example.com',
            'dt_registration' => $now->copy()->subDays(5),
            'status' => PaymentStatus::PENDING->value,
        ]);

        $stale = $repo->create([
            'id_order' => 10,
            'id_client' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'stale',
            'method_data' => ['foo' => 'bar'],
            'amount' => 75.00,
            'link' => null,
            'email' => 'a@example.com',
            'dt_registration' => $now->copy()->subDays(20),
            'status' => PaymentStatus::PENDING->value,
        ]);

        Carbon::setTestNow($now);

        $updated = $repo->cancelPendingBefore($now->copy()->subDays(14), PaymentStatus::CANCELED->value);

        $this->assertSame(1, $updated);
        $this->assertSame(PaymentStatus::PENDING->value, $repo->find($recent->id_payment)->status);
        $this->assertSame(PaymentStatus::CANCELED->value, $repo->find($stale->id_payment)->status);
    }

    private function migratePayments(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id_payment');
            $table->unsignedBigInteger('id_order')->nullable();
            $table->unsignedBigInteger('id_client')->nullable();
            $table->string('method');
            $table->string('method_id')->nullable();
            $table->json('method_data')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('link')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('dt_registration')->nullable();
            $table->timestamp('dt_executed')->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamps();
        });
    }
}
