<?php

/**
 * Test fixture for Payment model.
 *
 * Mock Eloquent model for testing without database.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Fixtures
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPayment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'method',
        'method_id',
        'amount',
        'fee',
        'status',
        'link',
        'result',
        'reason',
    ];

    protected $casts = [
        'amount' => 'float',
        'fee' => 'float',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set default values for testing
        $this->attributes['id'] = $this->attributes['id'] ?? uniqid();
        $this->attributes['status'] = $this->attributes['status'] ?? 'PENDING';
        $this->attributes['created_at'] = $this->attributes['created_at'] ?? now();
        $this->attributes['updated_at'] = $this->attributes['updated_at'] ?? now();
    }
}
