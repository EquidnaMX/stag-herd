<?php

namespace Equidna\StagHerd\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|string $id
 * @property string $provider
 * @property string $method
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string|null $provider_status
 * @property string|null $external_reference
 * @property string|null $payer_reference
 * @property string|null $payer_email
 * @property string|null $provider_payment_id
 * @property string|null $provider_order_id
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $raw_payload
 */
class StagHerdPayment extends Model
{
    protected $table = 'stag_herd_payments';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'raw_payload' => 'array',
    ];
}
