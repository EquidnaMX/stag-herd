<?php

/**
 * Enumeration of possible payment statuses.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Enums
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Enums;

/**
 * Represents the status of a payment transaction.
 */
enum PaymentStatus: string
{
    case APPROVED = 'APPROVED';
    case PENDING = 'PENDING';
    case REJECTED = 'REJECTED';
    case CANCELED = 'CANCELED';
    case DECLINED = 'DECLINED';
}
