<?php

/**
 * Test fixture for Order entity implementing PayableOrder contract.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Fixtures
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Fixtures;

use Equidna\StagHerd\Contracts\PayableClient;
use Equidna\StagHerd\Contracts\PayableOrder;

class TestOrder implements PayableOrder
{
    public function __construct(
        private int|string $id = 1,
        private ?PayableClient $client = null,
        private string $status = 'pending',
        private string $description = 'Test Order',
    ) {
        $this->client = $client ?? new TestClient();
    }

    public function getID(): int|string
    {
        return $this->id;
    }

    public function getClient(): PayableClient
    {
        return $this->client;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public static function fromID(int|string $id): static
    {
        return new static($id);
    }
}
