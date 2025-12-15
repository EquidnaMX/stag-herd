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
}
