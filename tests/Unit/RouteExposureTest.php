<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RouteExposureTest extends TestCase
{
    public function test_saved_payment_method_routes_are_absent_by_default(): void
    {
        $this->assertFalse(Route::has('stag-herd.payment-methods.index'));
        $this->assertFalse(Route::has('stag-herd.payment-methods.default'));
        $this->assertFalse(Route::has('stag-herd.payment-methods.deactivate'));
    }

    public function test_paypal_onboarding_referral_route_is_absent_by_default(): void
    {
        $this->assertFalse(Route::has('stag-herd.payments.paypal.onboarding.referral'));
    }
}
