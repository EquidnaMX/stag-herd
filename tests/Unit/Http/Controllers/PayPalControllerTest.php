<?php

namespace Equidna\StagHerd\Tests\Unit\Http\Controllers;

use Carbon\CarbonInterface;
use Equidna\StagHerd\Adapters\PayPalAdapter;
use Equidna\StagHerd\Contracts\PayableClient;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Http\Controllers\PayPalController;
use Equidna\StagHerd\Payment\PaymentManager;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;

class PayPalControllerTest extends TestCase
{
    public function test_return_url_captures_order_before_approving_payment(): void
    {
        $payment = new FakePaymentModel();
        $repository = new FakePaymentRepository($payment);

        $this->app->instance(PaymentRepository::class, $repository);
        $this->app->bind(PayableOrder::class, FakePayableOrder::class);
        $this->app->instance(PaymentManager::class, new PaymentManager($repository));

        config([
            'stag-herd.paypal.client_id' => 'client-id',
            'stag-herd.paypal.client_secret' => 'client-secret',
        ]);

        $paypal = new FakePayPalAdapter((object) [
            'id' => 'ORDER123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            [
                                'id' => 'CAPTURE123',
                                'status' => 'COMPLETED',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $request = Request::create('/stag-herd/paypal/return', 'GET', [
            'token' => 'ORDER123',
        ]);

        $response = (new PayPalController())->return($request, $paypal);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ORDER123', $paypal->capturedOrderId);
        $this->assertSame(PaymentStatus::APPROVED->value, $repository->payment->status);

        $methodData = json_decode($repository->payment->method_data, true);
        $this->assertSame('CAPTURE123', $methodData['capture_id']);
        $this->assertSame('COMPLETED', $methodData['capture_status']);
    }
}

class FakePayPalAdapter extends PayPalAdapter
{
    public ?string $capturedOrderId = null;

    public function __construct(private object $capture)
    {
    }

    public function captureOrder(string $orderId): object
    {
        $this->capturedOrderId = $orderId;

        return $this->capture;
    }
}

class FakePaymentRepository implements PaymentRepository
{
    public function __construct(public FakePaymentModel $payment)
    {
    }

    public function find(int|string $id): ?object
    {
        return (string) $id === (string) $this->payment->id_payment ? $this->payment : null;
    }

    public function findByMethodId(string $method, string $methodId): ?object
    {
        return $method === $this->payment->method && $methodId === $this->payment->method_id
            ? $this->payment
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): object
    {
        foreach ($data as $key => $value) {
            $this->payment->{$key} = $value;
        }

        return $this->payment;
    }

    public function save(object $paymentModel): object
    {
        $this->payment = $paymentModel;

        return $paymentModel;
    }

    public function deleteOrphans(): int
    {
        return 0;
    }

    public function pendingPayments(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $methods = [],
    ): LazyCollection {
        return LazyCollection::empty();
    }

    public function cancelPendingBefore(CarbonInterface $threshold, string $status): int
    {
        return 0;
    }
}

class FakePaymentModel
{
    public int $id_payment = 123;

    public int $id_order = 456;

    public int $id_client = 789;

    public string $method = 'PAYPAL';

    public string $method_id = 'ORDER123';

    public string $method_data = '{}';

    public float $amount = 100.0;

    public string $status = 'PENDING';

    public mixed $dt_executed = null;

    public mixed $uri_executor = null;

    public mixed $executor_type = null;
}

class FakePayableOrder implements PayableOrder
{
    public function getID(): int|string
    {
        return 456;
    }

    public function getClient(): PayableClient
    {
        return new FakePayableClient();
    }

    public function getDescription(): string
    {
        return 'Fake order';
    }

    public static function fromID(int|string $id): static
    {
        return new static();
    }
}

class FakePayableClient implements PayableClient
{
    public function getID(): int|string
    {
        return 789;
    }

    public function getEmail(): string
    {
        return 'buyer@example.com';
    }

    public function getName(): string
    {
        return 'Buyer';
    }
}
