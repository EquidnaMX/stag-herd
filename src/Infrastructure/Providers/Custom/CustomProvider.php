<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Custom;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;

final class CustomProvider extends AbstractPaymentProvider
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getMethods(): array
    {
        return [];
    }
}
