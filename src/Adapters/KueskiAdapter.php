<?php

/**
 * Adapter for Kueski Pay API integration (stub).
 *
 * Placeholder for Kueski Pay API integration. Requires implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Adapters
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Adapters;

use Exception;

class KueskiAdapter
{
    public function requestPayment(
        float $amount,
        string $description,
    ): object {
        throw new Exception('Kueski adapter not implemented');
    }
}
