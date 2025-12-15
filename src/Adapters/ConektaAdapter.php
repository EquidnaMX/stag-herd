<?php

/**
 * Adapter for Conekta payment API integration (stub).
 *
 * Placeholder for Conekta API integration. Requires implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Exception;

class ConektaAdapter
{
    public function requestPayment(
        float $amount,
        string $description,
    ): object {
        throw new Exception('Conekta adapter not implemented');
    }
}
