<?php

/**
 * Contract for payment persistence operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;

/**
 * Interface for payment persistence operations.
 */
interface PaymentRepository
{
    /**
     * Finds a payment by its primary identifier.
     *
     * @param  int|string   $id  Payment identifier.
     * @return object|null       Payment model or null if not found.
     */
    public function find(int|string $id): ?object;

    /**
     * Finds a payment by provider method ID.
     *
     * @param  string       $method    Payment method key.
     * @param  string       $methodId  Provider's payment ID.
     * @return object|null             Payment model or null if not found.
     */
    public function findByMethodId(string $method, string $methodId): ?object;

    /**
     * Creates a new payment record.
     *
     * @param  array<string, mixed> $data  Payment data.
     * @return object                      Created payment model.
     */
    public function create(array $data): object;

    /**
     * Persists changes to a payment model.
     *
     * @param  object $paymentModel  Payment model to save.
     * @return object                Saved payment model.
     */
    public function save(object $paymentModel): object;

    /**
     * Deletes payments without an associated order reference.
     *
     * @return int  Number of deleted payment records.
     */
    public function deleteOrphans(): int;

    /**
     * Retrieves pending payments within a registration date range.
     *
     * @param  CarbonInterface|null $from     Inclusive lower bound for the registration timestamp.
     * @param  CarbonInterface|null $to       Inclusive upper bound for the registration timestamp.
     * @param  array<int, string>   $methods  Payment method codes to include; empty for all.
     * @return LazyCollection<object>         Iterable list of pending payment models.
     */
    public function pendingPayments(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $methods = []
    ): LazyCollection;

    /**
     * Marks pending payments older than the threshold with the provided status.
     *
     * @param  CarbonInterface $threshold  Upper bound timestamp; payments older than this are updated.
     * @param  string          $status     Status value to set for stale payments.
     * @return int                         Number of updated payment records.
     */
    public function cancelPendingBefore(CarbonInterface $threshold, string $status): int;
}
