<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Http\Requests\Payments\PaymentFormRequest;

abstract class PayPalFormRequest extends PaymentFormRequest
{
    protected function resolveHostUrl(): string
    {
        $explicit = $this->input('return_url')
            ?? $this->input('cancel_url')
            ?? data_get($this->input('paypal'), 'return_url')
            ?? data_get($this->input('paypal'), 'cancel_url');

        if (is_string($explicit) && filter_var($explicit, FILTER_VALIDATE_URL)) {
            return $explicit;
        }

        $referer = $this->headers->get('referer');

        if (is_string($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            return $referer;
        }

        $origin = $this->headers->get('origin');

        if (is_string($origin) && filter_var($origin, FILTER_VALIDATE_URL)) {
            return $origin;
        }

        return rtrim((string) config('app.url', '/'), '/');
    }
}
