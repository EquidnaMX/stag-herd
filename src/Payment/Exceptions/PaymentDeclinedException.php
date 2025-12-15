<?php

/**
 * Exception thrown when a payment is declined.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Exceptions
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Exceptions;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;

/**
 * Indicates that the payment request was valid but could not be processed (declined).
 */
class PaymentDeclinedException extends UnprocessableEntityException
{
    // Inherits functionality from UnprocessableEntityException (422 Unprocessable Content)
}
