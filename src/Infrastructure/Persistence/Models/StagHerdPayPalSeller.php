<?php

namespace Equidna\StagHerd\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $seller_merchant_id
 * @property string|null $tracking_id
 * @property string|null $owner_reference
 * @property string|null $account_status
 * @property string|null $consent_status
 * @property array<int, string>|null $permissions
 * @property array<int, string>|null $capabilities
 * @property array<string, mixed>|null $integration
 * @property array<string, mixed>|null $payload
 */
final class StagHerdPayPalSeller extends Model
{
    protected $table = 'stag_herd_paypal_sellers';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'permissions' => 'array',
        'capabilities' => 'array',
        'integration' => 'array',
        'payload' => 'array',
    ];
}
