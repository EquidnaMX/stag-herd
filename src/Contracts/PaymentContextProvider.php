<?php

/**
 * Contract for resolving payment executor context.
 *
 * Provides the URI and type of the current executor initiating a payment.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

/**
 * Interface for retrieving executor context details.
 */
interface PaymentContextProvider
{
    /**
     * Returns the executor URI for the current context.
     *
     * @return string|null
     */
    public function getExecutorUri(): ?string;

    /**
     * Returns the executor type for the current context.
     *
     * @return string|null
     */
    public function getExecutorType(): ?string;
}
