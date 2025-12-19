<?php

/**
 * Tests for the payments cleanup console command.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Unit
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Unit;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Tests\TestCase;
use Mockery;

class PaymentsCleanupCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_runs_cleanup_steps_without_revalidation(): void
    {
        $now = Carbon::parse('2024-01-15 00:00:00');
        Carbon::setTestNow($now);

        config()->set('stag-herd.cleanup.revalidate.enabled', false);
        config()->set('stag-herd.cleanup.stale_pending_days', 14);
        config()->set('stag-herd.cleanup.stale_status', PaymentStatus::CANCELED->value);

        $repo = Mockery::mock(PaymentRepository::class);
        $repo->shouldReceive('deleteOrphans')->once()->andReturn(2);
        $repo->shouldReceive('pendingPayments')->never();
        $repo->shouldReceive('cancelPendingBefore')->once()->andReturn(5);

        $this->app->instance(PaymentRepository::class, $repo);

        $this->artisan('stag-herd:payments:clean')
            ->expectsOutput('Deleted 2 orphan payments without an order reference.')
            ->expectsOutput('Marked 5 pending payments older than 2024-01-01 00:00:00 as CANCELED.')
            ->assertSuccessful();
    }
}
