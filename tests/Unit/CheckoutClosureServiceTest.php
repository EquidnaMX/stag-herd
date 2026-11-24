<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\CheckoutClosureService;
use Equidna\StagHerd\Contracts\CredentialResolver;
use Equidna\StagHerd\Contracts\Gateways\ClosesMercadoPagoPreference;
use Equidna\StagHerd\Contracts\Gateways\ExpiresStripeCheckout;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\CheckoutClosureData;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Http;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeApiAdapter;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoApiAdapter;

final class CheckoutClosureServiceTest extends TestCase
{
    private function service(object $gateway, string $provider): CheckoutClosureService
    {
        $resolver = Mockery::mock(CredentialResolver::class);
        $resolver->shouldReceive('resolve')->with($provider, 'tenant-method')->once()->andReturn(['secret' => 'test']);

        return new CheckoutClosureService(new CredentialContextManager($resolver),
            $provider === 'stripe' ? $gateway : Mockery::mock(StripeGateway::class),
            $provider === 'paypal' ? $gateway : Mockery::mock(PayPalGateway::class),
            $provider === 'mercado_pago' ? $gateway : Mockery::mock(MercadoPagoGateway::class));
    }

    public function test_stripe_expires_unpaid_session_under_credential_context(): void
    {
        config(['stag-herd.providers.stripe.credentials' => ['original' => true]]);
        $gateway = Mockery::mock(StripeGateway::class, ExpiresStripeCheckout::class);
        $gateway->shouldReceive('getCheckoutSession')->once()->with('cs_1')->andReturn(['id' => 'cs_1', 'client_reference_id' => 'purchase', 'mode' => 'payment', 'status' => 'open']);
        $gateway->shouldReceive('expireCheckoutSession')->once()->with('cs_1')->andReturnUsing(function () {
            self::assertSame(['secret' => 'test'], config('stag-herd.providers.stripe.credentials'));
            return ['id' => 'cs_1', 'status' => 'expired', 'payment_status' => 'unpaid'];
        });
        self::assertTrue($this->service($gateway, 'stripe')->close(new CheckoutClosureData('stripe', 'cs_1', 'purchase', 'tenant-method'))->closed);
        self::assertSame(['original' => true], config('stag-herd.providers.stripe.credentials'));
    }

    public function test_stripe_complete_async_payment_is_never_closed(): void
    {
        $gateway = Mockery::mock(StripeGateway::class);
        $gateway->shouldReceive('getCheckoutSession')->once()->andReturn(['id' => 'cs_1', 'client_reference_id' => 'purchase', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'unpaid']);
        self::assertFalse($this->service($gateway, 'stripe')->close(new CheckoutClosureData('stripe', 'cs_1', 'purchase', 'tenant-method'))->closed);
    }

    public function test_stripe_does_not_expire_another_purchase(): void
    {
        $gateway = Mockery::mock(StripeGateway::class, ExpiresStripeCheckout::class);
        $gateway->shouldReceive('getCheckoutSession')->once()->andReturn(['id' => 'cs_1', 'client_reference_id' => 'other', 'mode' => 'payment', 'status' => 'open']);
        self::assertSame('reference_mismatch', $this->service($gateway, 'stripe')->close(new CheckoutClosureData('stripe', 'cs_1', 'purchase', 'tenant-method'))->reason);
    }

    public function test_paypal_requires_consumer_capture_fence_without_capturing_or_refunding(): void
    {
        foreach ([false, true] as $fenced) {
            $gateway = Mockery::mock(PayPalGateway::class);
            $gateway->shouldReceive('getOrder')->once()->with('ORDER1')->andReturn(['id' => 'ORDER1', 'intent' => 'CAPTURE', 'status' => 'APPROVED', 'purchase_units' => [['reference_id' => 'purchase']]]);
            $result = $this->service($gateway, 'paypal')->close(new CheckoutClosureData('paypal', 'ORDER1', 'purchase', 'tenant-method', $fenced));
            self::assertSame($fenced, $result->closed);
            self::assertSame($fenced, $result->requiresCaptureBlock);
        }
    }

    public function test_paypal_captured_order_requires_reconciliation(): void
    {
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('getOrder')->once()->andReturn(['id' => 'ORDER1', 'intent' => 'CAPTURE', 'status' => 'COMPLETED', 'purchase_units' => [['reference_id' => 'purchase', 'payments' => ['captures' => [['status' => 'PENDING']]]]]]);
        self::assertFalse($this->service($gateway, 'paypal')->close(new CheckoutClosureData('paypal', 'ORDER1', 'purchase', 'tenant-method', true))->closed);
    }

    public function test_mercado_pago_expires_preference_and_cancels_existing_pending_payment(): void
    {
        $gateway = Mockery::mock(MercadoPagoGateway::class, ClosesMercadoPagoPreference::class);
        $gateway->shouldReceive('getPreference')->twice()->with('pref_1')->andReturn(['id' => 'pref_1', 'external_reference' => 'purchase'], ['id' => 'pref_1', 'expires' => true, 'expiration_date_to' => '2020-01-01T00:00:00Z']);
        $gateway->shouldReceive('updatePreference')->once()->with('pref_1', Mockery::on(fn ($payload) => $payload['expires'] === true))->andReturn([]);
        $gateway->shouldReceive('searchPayments')->once()->andReturn(['results' => [['id' => 123, 'external_reference' => 'purchase']], 'paging' => ['total' => 1]]);
        $gateway->shouldReceive('getPayment')->once()->with('123')->andReturn(['id' => 123, 'external_reference' => 'purchase', 'status' => 'pending']);
        $gateway->shouldReceive('cancelPayment')->once()->with('123')->andReturn(['id' => 123, 'status' => 'cancelled']);
        self::assertTrue($this->service($gateway, 'mercado_pago')->close(new CheckoutClosureData('mercado_pago', 'pref_1', 'purchase', 'tenant-method'))->closed);
    }

    public function test_provider_adapters_use_only_expiration_endpoints(): void
    {
        Http::preventStrayRequests();
        config(['stag-herd.providers.stripe.credentials.secret_key' => 'test-key', 'stag-herd.providers.mercado_pago.credentials.access_token' => 'test-token']);
        Http::fake(['*' => Http::response(['id' => 'test'], 200)]);
        (new StripeApiAdapter())->expireCheckoutSession('cs_1');
        (new MercadoPagoApiAdapter())->getPreference('pref_1');
        (new MercadoPagoApiAdapter())->updatePreference('pref_1', ['expires' => true]);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/checkout/sessions/cs_1/expire'));
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/checkout/preferences/pref_1') && $request['expires'] === true);
    }

    public function test_mercado_pago_does_not_cancel_a_payment_that_completed_concurrently(): void
    {
        $gateway = Mockery::mock(MercadoPagoGateway::class, ClosesMercadoPagoPreference::class);
        $gateway->shouldReceive('getPreference')->twice()->andReturn(['id' => 'pref_1', 'external_reference' => 'purchase'], ['expires' => true, 'expiration_date_to' => '2020-01-01T00:00:00Z']);
        $gateway->shouldReceive('updatePreference')->once()->andReturn([]);
        $gateway->shouldReceive('searchPayments')->once()->andReturn(['results' => [['id' => 123, 'external_reference' => 'purchase']], 'paging' => ['total' => 1]]);
        $gateway->shouldReceive('getPayment')->once()->andReturn(['id' => 123, 'external_reference' => 'purchase', 'status' => 'approved']);
        self::assertFalse($this->service($gateway, 'mercado_pago')->close(new CheckoutClosureData('mercado_pago', 'pref_1', 'purchase', 'tenant-method'))->closed);
    }

    public function test_mercado_pago_checks_every_page_and_fails_closed_on_incomplete_search(): void
    {
        $gateway = Mockery::mock(MercadoPagoGateway::class, ClosesMercadoPagoPreference::class);
        $gateway->shouldReceive('getPreference')->twice()->andReturn(['id' => 'pref_1', 'external_reference' => 'purchase'], ['expires' => true, 'expiration_date_to' => '2020-01-01T00:00:00Z']);
        $gateway->shouldReceive('updatePreference')->once()->andReturn([]);
        $gateway->shouldReceive('searchPayments')->once()->with(['external_reference' => 'purchase', 'offset' => 0, 'limit' => 100])->andReturn(['results' => [['id' => 123, 'external_reference' => 'purchase']], 'paging' => ['total' => 2]]);
        $gateway->shouldReceive('getPayment')->once()->andReturn(['id' => 123, 'external_reference' => 'purchase', 'status' => 'cancelled']);
        $gateway->shouldReceive('searchPayments')->once()->with(['external_reference' => 'purchase', 'offset' => 1, 'limit' => 100])->andReturn(['results' => [], 'paging' => ['total' => 2]]);
        self::assertSame('payment_search_incomplete', $this->service($gateway, 'mercado_pago')->close(new CheckoutClosureData('mercado_pago', 'pref_1', 'purchase', 'tenant-method'))->reason);
    }
}
