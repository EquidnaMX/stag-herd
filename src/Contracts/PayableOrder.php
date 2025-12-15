<?php

/**
 * Contract for order entities that can be paid.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

/**
 * Interface for order entities capable of being paid.
 */
interface PayableOrder
{
    /**
     * Returns the order's unique identifier.
     *
     * @return int|string
     */
    public function getID(): int|string;

    /**
     * Returns the client associated with the order.
     *
     * @return PayableClient
     */
    public function getClient(): PayableClient;

    /**
     * Returns the order description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Loads an order by its identifier.
     *
     * @param  int|string $id
     * @return static
     */
    public static function fromID(int|string $id): static;
}
