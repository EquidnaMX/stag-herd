<?php

namespace Equidna\StagHerd\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $provider_event_created_at
 * @property array<string, mixed>|null $payload
 */
final class StagHerdBillingResource extends Model
{
    protected $table = 'stag_herd_billing_resources';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'provider_event_created_at' => 'integer',
    ];
}
