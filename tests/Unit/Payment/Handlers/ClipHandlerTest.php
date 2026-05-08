<?php

namespace Equidna\StagHerd\Tests\Unit\Payment\Handlers;

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
}
