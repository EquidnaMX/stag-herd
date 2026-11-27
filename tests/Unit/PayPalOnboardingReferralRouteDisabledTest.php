<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class PayPalOnboardingReferralRouteDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('stag-herd.providers.paypal.enabled', true);
        $app['config']->set('stag-herd.providers.paypal.routes.onboarding_referral.enabled', false);
    }

    public function test_paypal_onboarding_referral_route_is_absent_when_route_flag_is_disabled(): void
    {
        $this->assertFalse(Route::has('stag-herd.payments.paypal.onboarding.referral'));
    }
}
