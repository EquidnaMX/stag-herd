<?php

/**
 * Core payment domain model wrapper.
 *
 * Represents a payment entity and usage of the PaymentManager to perform actions.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use stdClass;

/**
 * Wrapper class for Payment models providing domain logic.
 */
final class Payment
{
    /**
     * Map of status codes to human-readable labels.
     *
     * @var array<string, string>
     */
    public const VALID_STATUS = [
        'APPROVED' => 'Aprobado',
        'PENDING' => 'Pendiente',
        'REJECTED' => 'Rechazado',
        'CANCELED' => 'Cancelado',
    ];

    private PaymentHandler $payment_handler;

    /**
     * Creates a new Payment wrapper instance.
     *
     * @param  mixed                   $payment_model  The underlying storage model.
     * @param  PaymentRepository|null  $repository     Repository instance (optional).
     */
    public function __construct(
        private $payment_model,
        private ?PaymentRepository $repository = null
    ) {
        $this->repository = $repository ?? app(PaymentRepository::class);

        // Re-hydrate context needed for the handler
        $order = app(PayableOrder::class)::fromID($payment_model->id_order);
        $manager = app(PaymentManager::class);

        $this->payment_handler = $manager->getHandler(
            method: $payment_model->method,
            amount: $payment_model->amount,
            order: $order,
            method_data: PaymentData::fromMixed(json_decode($payment_model->method_data)),
        );
    }

    /**
     * Retrieves all configured payment methods.
     *
     * @param  bool $onlyEnabled  If true, filters out disabled methods.
     * @return array              Configuration array of methods.
     */
    public static function getMethods(bool $onlyEnabled = false): array
    {
        $methods = config('stag-herd.methods', []);

        if (!$onlyEnabled) {
            return $methods;
        }

        return array_filter($methods, function ($method) {
            return !empty($method['enabled']);
        });
    }

    /**
     * Factory: Retrieves a payment by its ID.
     *
     * @param  int|string $id_payment  The payment identifier.
     * @return static                  The payment instance.
     */
    public static function fromID(int|string $id_payment): static
    {
        return app(PaymentManager::class)->fromID($id_payment);
    }

    /**
     * Factory: Wraps an existing model instance.
     *
     * @param  mixed $payment  The model instance.
     * @return static          The wrapped payment instance.
     */
    public static function fromModel($payment): static
    {
        return new static($payment);
    }

    /**
     * Factory: Retrieves a payment by provider method ID.
     *
     * @param  string $method     The payment method key.
     * @param  string $method_id  The provider's payment ID.
     * @return self               The payment instance.
     */
    public static function fromMethodID(string $method, string $method_id): self
    {
        return app(PaymentManager::class)->fromMethodID($method, $method_id);
    }

    /**
     * Factory: Requests a new payment.
     *
     * @param  float         $amount       Amount to charge.
     * @param  string        $method       Payment method.
     * @param  PayableOrder  $order        Order context.
     * @param  mixed         $method_data  Extra data.
     * @return static                      The new payment instance.
     */
    public static function request(
        float $amount,
        string $method,
        PayableOrder $order,
        mixed $method_data = null
    ): static {
        return app(PaymentManager::class)->request($amount, $method, $order, $method_data);
    }

    /**
     * Attempts to approve the payment.
     *
     * Delegates to the handler and updates the model if approved.
     *
     * @return \Equidna\StagHerd\Data\PaymentResult  Result object.
     */
    public function approvePayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        $result = $this->payment_handler->approvePayment($this->payment_model);

        if ($result->result != PaymentStatus::PENDING->value) {
            $this->payment_model->status = $result->result;
            $this->payment_model->dt_executed = Carbon::now();
            $this->repository->save($this->payment_model);

            if ($result->result == PaymentStatus::APPROVED->value) {
                \Equidna\StagHerd\Events\PaymentApproved::dispatch($this);
            }
        }

        return $result;
    }

    /**
     * Attempts to cancel the payment.
     *
     * @return static                    The updated payment instance.
     * @throws PaymentDeclinedException  If cancellation fails.
     */
    public function cancelPayment(): static
    {
        if ($this->payment_model->status == PaymentStatus::CANCELED->value) {
            return $this;
        }

        $result = $this->payment_handler->cancelPayment($this->payment_model);

        if ($result->result != PaymentStatus::CANCELED->value) {
            throw new PaymentDeclinedException('Payment can not be canceled - ' . $result->reason);
        }

        $this->payment_model->status = PaymentStatus::CANCELED->value;
        $this->payment_model->dt_executed = Carbon::now();
        $this->repository->save($this->payment_model);

        return $this;
    }

    /**
     * Gets the payment ID.
     *
     * @return int|string
     */
    public function getID(): int|string
    {
        return $this->payment_model->id_payment;
    }

    /**
     * Gets the payment method Enum.
     *
     * @return PaymentMethod
     */
    public function getMethod(): PaymentMethod
    {
        return PaymentMethod::tryFrom($this->payment_model->method)
            ?? PaymentMethod::tryFrom('CUSTOM')
            ?? $this->payment_model->method;
    }

    /**
     * Gets the payment status Enum.
     *
     * @return PaymentStatus
     */
    public function getStatus(): PaymentStatus
    {
        return PaymentStatus::tryFrom($this->payment_model->status) ?? PaymentStatus::PENDING;
    }

    /**
     * Gets the CFDI code for the method.
     *
     * @return string|null
     */
    public function methodCFDI(): ?string
    {
        return $this->payment_handler::CFDI;
    }

    /**
     * Gets the underlying payment model.
     *
     * @return mixed
     */
    public function getPaymentModel()
    {
        return $this->payment_model;
    }

    /**
     * Magic getter to delegate property access to the underlying model.
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->payment_model->{$name} ?? null;
    }
}
