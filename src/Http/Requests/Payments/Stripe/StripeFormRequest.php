<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

use Equidna\StagHerd\Http\Requests\Payments\PaymentFormRequest;

abstract class StripeFormRequest extends PaymentFormRequest
{
    protected function resolvedExternalReference(string $prefix, ?string $value): string
    {
        return $value ?: $prefix . '-' . now()->format('YmdHis');
    }
}
