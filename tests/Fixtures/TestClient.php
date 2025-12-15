<?php

/**
 * Test fixture for Client entity implementing PayableClient contract.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Fixtures
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Fixtures;

use Equidna\StagHerd\Contracts\PayableClient;

class TestClient implements PayableClient
{
    public function __construct(
        private int|string $id = 1,
        private string $name = 'Test Client',
        private string $email = 'test@example.com',
    ) {
        //
    }

    public function getID(): int|string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
