<?php

namespace Equidna\StagHerd\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class StagHerdPayment extends Model
{
    protected $table = 'stag_herd_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'raw_payload' => 'array',
    ];
}
