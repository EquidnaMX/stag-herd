<?php

/**
 * Exception thrown when an invalid payment method is provided.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Exceptions
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Exceptions;

use Equidna\Toolkit\Exceptions\BadRequestException;

/**
 * Indicates that the payment method is invalid or malformed.
 */
class InvalidPaymentMethodException extends BadRequestException
{
    // Inherits functionality from BadRequestException (400 Bad Request)
}
