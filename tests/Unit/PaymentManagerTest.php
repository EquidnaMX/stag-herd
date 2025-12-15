<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Payment\PaymentManager;
use Equidna\StagHerd\Tests\Fixtures\TestClient;
use Equidna\StagHerd\Tests\Fixtures\TestOrder;
use Equidna\StagHerd\Tests\Fixtures\TestPayment;
use Equidna\StagHerd\Tests\TestCase;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Mockery;

class PaymentManagerTest extends TestCase
{
    protected PaymentManager $manager;

    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the config for methods
        config()->set('stag-herd.methods', [
            'TEST_METHOD' => [
                'handler' => \Equidna\StagHerd\Tests\Fixtures\TestHandler::class,
            ],
        ]);

        $this->repository = Mockery::mock(PaymentRepository::class);
        $this->manager = new PaymentManager($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRequestPaymentCreateSuccess()
    {
        $client = new TestClient(id: 1, email: 'test@example.com');
        $order = new TestOrder(id: 100, client: $client);

        // Expect finding method ID duplicate check
        $this->repository->shouldReceive('findByMethodId')
            ->once()
            ->with('TEST_METHOD', 'test_id_mocked') // TestHandler generates random ID, so we need to control it or allow any
            ->andReturn(null);

        // Wait, TestHandler logic:
        // $result->method_id = 'test_id_' . uniqid();
        // We can't predict uniqid.
        // We should mock the Handler too? Or let it run?
        // If we let it run, we can't assert strict args on 'create' easily unless we use Mockery::capture or similar.

        // But wait, the Duplicate Check in PaymentManager:
        // $handler::ALLOW_DUPLICATED_METHOD_ID is true by default for PaymentHandler?
        // PaymentHandler base: const ALLOW_DUPLICATED_METHOD_ID = false;
        // TestHandler extends PaymentHandler.
        // So duplicate check RUNS if method_id is present.
        // But data->payment_method_id comes from input method_data.
        // In this test: method_data: ['token' => ...]. DTO payment_method_id is null.
        // So duplicate check is SKIPPED.

        // So we only expect create.
        $this->repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function ($data) {
                return new TestPayment($data);
            });

        $this->repository->shouldReceive('save')->andReturn(true);

        $payment = $this->manager->request(
            amount: 100.00,
            method: 'TEST_METHOD',
            order: $order,
            method_data: ['token' => 'tok_123']
        );

        $this->assertEquals('PENDING', $payment->getStatus()->value);
        $this->assertEquals(100.00, $payment->getPaymentModel()->amount);
    }

    public function testRequestPaymentInvalidMethod()
    {
        $client = new TestClient(id: 1, email: 'test@example.com');
        $order = new TestOrder(id: 100, client: $client);

        $this->expectException(BadRequestException::class);

        $this->manager->request(
            amount: 100.00,
            method: 'INVALID_METHOD',
            order: $order
        );
    }
}
