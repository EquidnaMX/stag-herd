<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class PayPalOnboardingReferralRouteEnabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('stag-herd.providers.paypal.enabled', true);
        $app['config']->set('stag-herd.providers.paypal.routes.onboarding_referral.enabled', true);
    }

    public function test_paypal_onboarding_referral_route_is_present_when_paypal_and_route_flag_are_enabled(): void
    {
        $this->assertTrue(Route::has('stag-herd.payments.paypal.onboarding.referral'));
    }
}
