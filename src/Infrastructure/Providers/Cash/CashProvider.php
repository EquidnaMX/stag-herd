<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Cash;

use Equidna\StagHerd\Infrastructure\Providers\AbstractPaymentProvider;
use Equidna\StagHerd\Infrastructure\Providers\Cash\Handlers\CashPaymentHandler;
use Equidna\StagHerd\Support\PaymentMethodHandlerRegistry;

final class CashProvider extends AbstractPaymentProvider
{
    public function __construct(?PaymentMethodHandlerRegistry $handlers = null)
    {
        if (!$handlers instanceof PaymentMethodHandlerRegistry) {
            $handlers = new PaymentMethodHandlerRegistry();
            $handlers->register(new CashPaymentHandler());
        }

        parent::__construct($handlers);
    }

    public function getName(): string
    {
        return 'cash';
    }

    public function getMethods(): array
    {
        return ['cash'];
    }
}
