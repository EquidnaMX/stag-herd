<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;

final class MercadoPagoProvider extends AbstractPaymentProvider
{
    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function getMethods(): array
    {
        return ['card', 'checkout_pro'];
    }
}
