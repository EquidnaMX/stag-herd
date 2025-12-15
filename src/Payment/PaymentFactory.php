<?php

/**
 * Payment factory for simplified instantiation.
 *
 * Provides static factory methods for creating Payment instances without direct dependency injection.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment;

use Equidna\StagHerd\Payment\Payment as PaymentDomain;

/**
 * Factory for creating Payment instances.
 */
final class PaymentFactory
{
    /**
     * Retrieves a payment by provider method ID.
     *
     * @param  string $method     The payment method key.
     * @param  string $methodId   The provider's payment ID.
     * @return PaymentDomain      The payment instance.
     */
    public static function fromMethodID(string $method, string $methodId): PaymentDomain
    {
        return PaymentDomain::fromMethodID($method, $methodId);
    }
}
