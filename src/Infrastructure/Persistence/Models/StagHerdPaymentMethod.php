<?php

namespace Equidna\StagHerd\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $provider_event_created_at */
final class StagHerdPaymentMethod extends Model
{
    protected $table = 'stag_herd_payment_methods';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
        'payload' => 'array',
        'attached_at' => 'datetime',
        'detached_at' => 'datetime',
        'last_used_at' => 'datetime',
        'provider_event_created_at' => 'integer',
    ];
}
