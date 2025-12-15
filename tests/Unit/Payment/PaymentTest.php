<?php

/**
 * Unit tests for Payment domain class.
 *
 * Tests core payment functionality including method registration,
 * handler selection, and payment lifecycle.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Tests\Unit\Payment
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Tests\Unit\Payment;

use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Tests\Fixtures\TestOrder;
use Equidna\StagHerd\Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a test payment method
        config([
            'stag-herd.methods' => [
                'TEST_METHOD' => [
                    'handler' => TestPaymentHandler::class,
                    'description' => 'Test Payment Method',
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function test_retrieves_available_payment_methods()
    {
        $methods = Payment::getMethods();

        $this->assertIsArray($methods);
        $this->assertArrayHasKey('TEST_METHOD', $methods);
        $this->assertEquals('Test Payment Method', $methods['TEST_METHOD']['description']);
    }

    public function test_filters_enabled_payment_methods()
    {
        config([
            'stag-herd.methods' => [
                'ENABLED_METHOD' => [
                    'handler' => TestPaymentHandler::class,
                    'description' => 'Enabled',
                    'enabled' => true,
                ],
                'DISABLED_METHOD' => [
                    'handler' => TestPaymentHandler::class,
                    'description' => 'Disabled',
                    'enabled' => false,
                ],
            ],
        ]);

        $methods = Payment::getMethods(onlyEnabled: true);

        $this->assertArrayHasKey('ENABLED_METHOD', $methods);
        $this->assertArrayNotHasKey('DISABLED_METHOD', $methods);
    }

    public function test_throws_exception_for_invalid_payment_method()
    {
        $this->expectException(InvalidPaymentMethodException::class);

        Payment::request(
            amount: 100.00,
            method: 'INVALID_METHOD',
            order: new TestOrder()
        );
    }

    public function test_calculates_payment_fees_correctly()
    {
        $handler = new TestPaymentHandler(
            amount: 100.00,
            order: new TestOrder(),
            method_data: null
        );

        $fee = $handler->getFee();

        // Test handler has 10% + $5 fee
        $expectedFee = (100.00 * 0.10) + 5;
        $this->assertEquals($expectedFee, $fee);
    }

    public function test_validates_payment_status_constants()
    {
        $validStatuses = Payment::VALID_STATUS;

        $this->assertIsArray($validStatuses);
        $this->assertArrayHasKey('APPROVED', $validStatuses);
        $this->assertArrayHasKey('PENDING', $validStatuses);
        $this->assertArrayHasKey('REJECTED', $validStatuses);
        $this->assertArrayHasKey('CANCELED', $validStatuses);
    }
}

/**
 * Mock payment handler for testing.
 */
class TestPaymentHandler extends \Equidna\StagHerd\Payment\Handlers\PaymentHandler
{
    public const PAYMENT_METHOD = 'TEST_METHOD';

    public const VARIABLE_FEE_RATE = 0.10;

    public const FIXED_FEE = 5;

    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        return \Equidna\StagHerd\Data\PaymentResult::pending(
            method_id: 'test-' . uniqid()
        );
    }

    protected function validatePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'APPROVED',
            method_id: (string) $paymentModel->method_id
        );
    }
}
