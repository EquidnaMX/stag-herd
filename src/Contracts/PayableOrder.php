<?php

/**
 * Contract for order entities that can be paid.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

interface PayableOrder
{
    public function getID(): int|string;

    public function getClient(): PayableClient;

    public function getDescription(): string;
}
