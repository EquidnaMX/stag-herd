<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class SavedPaymentMethodRoutesEnabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('stag-herd.payment_methods.routes.enabled', true);
    }

    public function test_saved_payment_method_routes_are_present_when_enabled(): void
    {
        $this->assertTrue(Route::has('stag-herd.payment-methods.index'));
        $this->assertTrue(Route::has('stag-herd.payment-methods.default'));
        $this->assertTrue(Route::has('stag-herd.payment-methods.deactivate'));
    }
}
