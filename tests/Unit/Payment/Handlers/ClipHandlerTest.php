<?php

namespace Equidna\StagHerd\Tests\Unit\Payment\Handlers;

use Equidna\StagHerd\Contracts\ClipGateway;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Handlers\ClipHandler;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Http\Request;

class ClipHandlerTest extends TestCase
{
    public function test_clip_webhook_rejects_payload_without_payment_request_id(): void
    {
        $request = Request::create('/webhook/clip', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"resource_status":"COMPLETED"}');

        $result = ClipHandler::verifyWebhook($request);

        $this->assertFalse($result['valid']);
        $this->assertSame('Missing Clip payment_request_id', $result['reason']);
    }

    public function test_clip_webhook_uses_payment_request_id_for_idempotency(): void
    {
        $request = Request::create('/webhook/clip', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"payment_request_id":"aeb68a0e-f780-4636-8655-5b11e3f7b8b2","resource_status":"COMPLETED","receipt_no":"T96suhh"}');

        $result = ClipHandler::verifyWebhook($request);

        $this->assertTrue($result['valid']);
        $this->assertSame('aeb68a0e-f780-4636-8655-5b11e3f7b8b2:T96suhh:COMPLETED', $result['eventId']);
    }

    public function test_clip_handler_uses_injected_gateway_contract(): void
    {
        $gateway = new FakeClipGateway((object) [
            'payment_request_id' => 'clip_123',
            'payment_request_url' => 'https://pay.clip.mx/clip_123',
            'status' => 'CREATED',
        ]);

        $handler = new ClipHandler(
            amount: 100.0,
            order: null,
            method_data: null,
            clip_adapter: $gateway,
        );

        $result = $handler->requestPayment();

        $this->assertSame(PaymentStatus::PENDING->value, $result->result);
        $this->assertSame('clip_123', $result->method_id);
        $this->assertSame('https://pay.clip.mx/clip_123', $result->link);
        $this->assertTrue($gateway->requestPaymentCalled);
    }
}

class FakeClipGateway implements ClipGateway
{
    public bool $requestPaymentCalled = false;

    public function __construct(private object $createdPayment)
    {
    }

    public function requestPayment(float $amount, string $description, array $options = []): object
    {
        $this->requestPaymentCalled = true;

        return $this->createdPayment;
    }

    public function getPaymentDetails(string $paymentId): object
    {
        throw new \RuntimeException('No existing Clip payment');
    }

    public function getRefund(string $paymentId, float $amount): object
    {
        return (object) [
            'id' => $paymentId,
            'amount' => $amount,
        ];
    }
}
