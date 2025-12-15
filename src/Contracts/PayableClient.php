<?php

/**
 * Contract for client entities that can make payments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

/**
 * Interface for client entities capable of making payments.
 */
interface PayableClient
{
    /**
     * Returns the client's unique identifier.
     *
     * @return int|string
     */
    public function getID(): int|string;

    /**
     * Returns the client's email address.
     *
     * @return string
     */
    public function getEmail(): string;

    /**
     * Returns the client's name.
     *
     * @return string
     */
    public function getName(): string;
}
