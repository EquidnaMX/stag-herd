<?php

/**
 * Unit tests for EloquentPaymentRepository.
 *
 * Tests payment persistence layer with mock model.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Unit\Repositories
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Unit\Repositories;

use Equidna\StagHerd\Repositories\EloquentPaymentRepository;
use Equidna\StagHerd\Tests\Fixtures\TestPayment;
use Equidna\StagHerd\Tests\TestCase;
use Exception;

class EloquentPaymentRepositoryTest extends TestCase
{
    private EloquentPaymentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stag-herd.payment_model', TestPayment::class);
        $this->repository = new EloquentPaymentRepository();
    }

    public function test_resolves_model_class_from_config()
    {
        $repository = new EloquentPaymentRepository();

        $reflection = new \ReflectionClass($repository);
        $property = $reflection->getProperty('modelClass');
        $property->setAccessible(true);

        $this->assertEquals(TestPayment::class, $property->getValue($repository));
    }

    public function test_accepts_custom_model_class_in_constructor()
    {
        $repository = new EloquentPaymentRepository(TestPayment::class);

        $reflection = new \ReflectionClass($repository);
        $property = $reflection->getProperty('modelClass');
        $property->setAccessible(true);

        $this->assertEquals(TestPayment::class, $property->getValue($repository));
    }

    public function test_throws_exception_for_invalid_model_class()
    {
        config()->set('stag-herd.payment_model', 'InvalidClass');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Payment model class not found');

        new EloquentPaymentRepository();
    }

    public function test_creates_payment_with_data()
    {
        $data = [
            'order_id' => 1,
            'method' => 'PAYPAL',
            'method_id' => 'PAY-123',
            'amount' => 100.00,
            'fee' => 5.00,
            'status' => 'PENDING',
        ];

        $payment = $this->repository->create($data);

        $this->assertInstanceOf(TestPayment::class, $payment);
        $this->assertEquals('PAYPAL', $payment->method);
        $this->assertEquals(100.00, $payment->amount);
    }

    public function test_saves_eloquent_model()
    {
        $payment = new TestPayment([
            'order_id' => '1',
            'method' => 'TEST',
            'amount' => 50.00,
        ]);

        $result = $this->repository->save($payment);

        $this->assertTrue($result);
    }

    public function test_throws_exception_when_saving_non_eloquent_model()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not an Eloquent instance');

        $this->repository->save(new \stdClass());
    }
}
