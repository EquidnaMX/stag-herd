<?php

namespace Equidna\StagHerd\Tests\Unit\Payment;

use Carbon\CarbonInterface;
use Equidna\StagHerd\Contracts\PayableClient;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Payment\PaymentManager;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\LazyCollection;

class PaymentStateMachineTest extends TestCase
{
    public function test_rejected_payment_cannot_be_approved_later(): void
    {
        [$payment, $repository] = $this->makePayment(PaymentStatus::REJECTED);

        $result = $payment->applyResult(PaymentResult::success(
            result: PaymentStatus::APPROVED->value,
            method_id: 'fake_method_id',
        ));

        $this->assertTrue($result->error);
        $this->assertSame(PaymentStatus::REJECTED->value, $repository->payment->status);
        $this->assertSame(0, $repository->saveCount);
    }

    public function test_approved_payment_can_be_refunded(): void
    {
        [$payment, $repository] = $this->makePayment(PaymentStatus::APPROVED);

        $result = $payment->applyResult(PaymentResult::refunded('Provider refund'));

        $this->assertFalse($result->error);
        $this->assertSame(PaymentStatus::REFUNDED->value, $repository->payment->status);
        $this->assertSame(1, $repository->saveCount);
    }

    /**
     * @return array{0: Payment, 1: StateMachinePaymentRepository}
     */
    private function makePayment(PaymentStatus $status): array
    {
        $model = new StateMachinePaymentModel($status);
        $repository = new StateMachinePaymentRepository($model);

        $this->app->instance(PaymentRepository::class, $repository);
        $this->app->bind(PayableOrder::class, StateMachinePayableOrder::class);
        $this->app->instance(PaymentManager::class, new PaymentManager($repository));

        config([
            'stag-herd.methods.FAKE' => [
                'handler' => StateMachinePaymentHandler::class,
                'enabled' => true,
                'fee' => [
                    'fixed' => 0,
                    'variable' => 0,
                ],
            ],
        ]);

        return [Payment::fromModel($model), $repository];
    }
}

class StateMachinePaymentHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = 'FAKE';
}

class StateMachinePaymentRepository implements PaymentRepository
{
    public int $saveCount = 0;

    public function __construct(public StateMachinePaymentModel $payment)
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
        $this->saveCount++;
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

class StateMachinePaymentModel
{
    public int $id_payment = 123;

    public int $id_order = 456;

    public int $id_client = 789;

    public string $method = 'FAKE';

    public string $method_id = 'fake_method_id';

    public string $method_data = '{}';

    public float $amount = 100.0;

    public string $status;

    public mixed $dt_executed = null;

    public mixed $uri_executor = null;

    public mixed $executor_type = null;

    public function __construct(PaymentStatus $status)
    {
        $this->status = $status->value;
    }
}

class StateMachinePayableOrder implements PayableOrder
{
    public function getID(): int|string
    {
        return 456;
    }

    public function getClient(): PayableClient
    {
        return new StateMachinePayableClient();
    }

    public function getDescription(): string
    {
        return 'State machine order';
    }

    public static function fromID(int|string $id): static
    {
        return new static();
    }
}

class StateMachinePayableClient implements PayableClient
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
