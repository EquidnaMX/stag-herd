<?php

/**
 * Contract for payment persistence operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Contracts
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Contracts;

interface PaymentRepository
{
    public function find(int|string $id);

    public function findByMethodId(string $method, string $methodId);

    public function create(array $data);

    public function save($paymentModel);
}
