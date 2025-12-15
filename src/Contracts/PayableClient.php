<?php

/**
 * Contract for client entities that can make payments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

interface PayableClient
{
    public function getID(): int|string;

    public function getEmail(): string;

    public function getName(): string;
}
