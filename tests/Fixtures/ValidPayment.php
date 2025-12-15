<?php

namespace Equidna\StagHerd\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class ValidPayment extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'id_payment';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_order',
        'id_client',
        'method',
        'method_id',
        'method_data',
        'amount',
        'link',
        'email',
        'dt_registration',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'method_data' => 'array',
        'dt_registration' => 'datetime',
        'dt_executed' => 'datetime',
    ];
}
