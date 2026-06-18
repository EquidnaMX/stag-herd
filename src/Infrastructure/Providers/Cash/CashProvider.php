<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Cash;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;

final class CashProvider extends AbstractPaymentProvider
{
    public function getName(): string
    {
        return 'cash';
    }

    public function getMethods(): array
    {
        return ['cash'];
    }
}
