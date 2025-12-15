<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Repositories\EloquentPaymentRepository;
use Equidna\StagHerd\Tests\Fixtures\ValidPayment;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PaymentModelContractTest extends TestCase
{
    public function test_missing_payment_model_config_throws(): void
    {
        config()->set('stag-herd.payment_model', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stag-herd.payment_model is not configured');

        new EloquentPaymentRepository();
    }

    public function test_configured_model_accepts_required_attributes(): void
    {
        config()->set('stag-herd.payment_model', ValidPayment::class);

        $class = config('stag-herd.payment_model');
        $this->assertTrue(class_exists($class), 'Configured payment model class does not exist');
        $this->assertTrue(is_subclass_of($class, Model::class), 'Payment model must extend Eloquent Model');

        /** @var Model $model */
        $model = new $class();

        $required = [
            'id_order',
            'id_client',
            'method',
            'method_id',
            'method_data',
            'amount',
            'link',
            'email',
            'dt_registration',
            'status',
        ];

        $payload = [
            'id_order' => 100,
            'id_client' => 1,
            'method' => 'TEST',
            'method_id' => 'm_1',
            'method_data' => ['foo' => 'bar'],
            'amount' => 123.45,
            'link' => 'https://example.com/pay',
            'email' => 'test@example.com',
            'dt_registration' => now(),
            'status' => 'PENDING',
        ];

        // Filling should not drop required attributes (fillable or unguarded)
        $model->fill($payload);

        foreach ($required as $key) {
            $this->assertArrayHasKey(
                $key,
                $model->getAttributes(),
                "Model must accept attribute: {$key}"
            );
        }
    }
}
