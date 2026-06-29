<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;

final class StripeProvider extends AbstractPaymentProvider
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function getMethods(): array
    {
        return ['card'];
    }
}
