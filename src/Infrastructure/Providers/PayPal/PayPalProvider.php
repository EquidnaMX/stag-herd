<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;

final class PayPalProvider extends AbstractPaymentProvider
{
    public function getName(): string
    {
        return 'paypal';
    }

    public function getMethods(): array
    {
        return ['paypal'];
    }
}
