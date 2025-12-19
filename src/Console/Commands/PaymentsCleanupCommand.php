<?php

/**
 * Console command for cleaning payment records.
 *
 * Removes orphan payments, optionally revalidates recent pending payments,
 * and cancels stale pending payments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Console\Commands
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Console\Commands;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentsCleanupCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected $signature = 'stag-herd:payments:clean
        {--revalidate : Force revalidation of recent pending payments}
        {--skip-revalidate : Skip revalidation even if enabled in config}';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Deletes orphan payments, revalidates recent pending payments, and cancels stale pending ones.';

    public function __construct(private PaymentRepository $payments)
    {
        parent::__construct();
    }

    /**
     * Executes the cleanup routine.
     *
     * @return int  Exit code (0 on success).
     */
    public function handle(): int
    {
        $deletedOrphans = $this->payments->deleteOrphans();
        $this->info("Deleted {$deletedOrphans} orphan payments without an order reference.");

        $revalidated = $this->maybeRevalidatePending();

        if (!is_null($revalidated)) {
            $this->info("Revalidated {$revalidated} pending payments created recently.");
        }

        $staleCutoff = Carbon::now()->subDays((int) config('stag-herd.cleanup.stale_pending_days', 14));
        $staleStatus = (string) config('stag-herd.cleanup.stale_status', PaymentStatus::CANCELED->value);
        $staleUpdated = $this->payments->cancelPendingBefore($staleCutoff, $staleStatus);
        $this->info("Marked {$staleUpdated} pending payments older than {$staleCutoff->toDateTimeString()} as {$staleStatus}.");

        return self::SUCCESS;
    }

    /**
     * Attempts revalidation of pending payments when enabled.
     *
     * @return int|null  Count of payments revalidated, or null when skipped.
     */
    private function maybeRevalidatePending(): ?int
    {
        $configEnabled = (bool) config('stag-herd.cleanup.revalidate.enabled', false);

        if ($this->option('skip-revalidate')) {
            return null;
        }

        if (!$configEnabled && !$this->option('revalidate')) {
            return null;
        }

        $lookbackHours = (int) config('stag-herd.cleanup.revalidate.lookback_hours', 24);

        if ($lookbackHours <= 0) {
            return 0;
        }

        $since = Carbon::now()->subHours($lookbackHours);
        $methods = (array) config('stag-herd.cleanup.revalidate.methods', []);

        $count = 0;

        foreach ($this->payments->pendingPayments($since, null, $methods) as $model) {
            try {
                $payment = new Payment($model, $this->payments);
                $result = $payment->approvePayment();

                if ($result->result !== PaymentStatus::PENDING->value) {
                    $count++;
                }
            } catch (Throwable $exception) {
                Log::warning('Failed to revalidate payment', [
                    'payment_id' => $model->id ?? null,
                    'method_id' => $model->method_id ?? null,
                    'method' => $model->method ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
