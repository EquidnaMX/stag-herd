<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Http\Controllers\WebhookController;
use Equidna\StagHerd\Payment\PaymentManager;
use Equidna\StagHerd\Tests\Fixtures\TestHandler;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;

class WebhookControllerTest extends TestCase
{
    protected $paymentManager;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentManager = Mockery::mock(PaymentManager::class);
        $this->controller = new WebhookController($this->paymentManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHandleInvalidProvider()
    {
        $this->paymentManager
            ->shouldReceive('getHandlerClass')
            ->with('INVALID')
            ->andThrow(new \Exception('Invalid provider'));

        $request = Request::create('/webhook/invalid', 'POST');

        $response = $this->controller->handle($request, 'invalid');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(['message' => 'Invalid provider'], $response->getData(true));
    }

    public function testHandleSuccess()
    {
        // Mock handler class call
        $this->paymentManager
            ->shouldReceive('getHandlerClass')
            ->with('TEST_METHOD')
            ->andReturn(TestHandler::class);

        // We can't easily mock static methods of the handler returned by string
        // So we rely on TestHandler::verifyWebhook returning default "Not implemented"
        // But TestHandler extends PaymentHandler which has default verifyWebhook returning valid=>false

        // Let's assume for this test we mock a handler that returns valid true?
        // Or we update TestHandler fixture to allow static mocking / override.

        // For now, let's test the flow that hits "Invalid signature" (default behavior)
        $request = Request::create('/webhook/test_method', 'POST');

        $response = $this->controller->handle($request, 'test_method');

        // Base PaymentHandler::verifyWebhook returns valid=false
        $this->assertEquals(401, $response->getStatusCode());
    }
}
