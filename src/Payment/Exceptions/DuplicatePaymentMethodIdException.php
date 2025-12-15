<?php

/**
 * Exception thrown when a duplicate payment method ID is detected.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Exceptions
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Exceptions;

use Equidna\Toolkit\Exceptions\ConflictException;

/**
 * Indicates a conflict due to a duplicated payment method identifier.
 */
class DuplicatePaymentMethodIdException extends ConflictException
{
    // Inherits functionality from ConflictException (409 Conflict)
}
