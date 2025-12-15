<?php

/**
 * Exception thrown when a payment cannot be found.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Exceptions
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Exceptions;

use Equidna\Toolkit\Exceptions\NotFoundException;

/**
 * Indicates that a requested payment resource was not found.
 */
class PaymentNotFoundException extends NotFoundException
{
    // Inherits functionality from NotFoundException (404 Not Found)
}
