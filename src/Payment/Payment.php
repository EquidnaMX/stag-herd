<?php

/**
 * Core payment domain model wrapper.
 *
 * Represents a payment entity and usage of the PaymentManager to perform actions.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Contracts\PaymentContextProvider;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Events\PaymentApproved;
use Equidna\StagHerd\Events\PaymentCanceled;
use Equidna\StagHerd\Events\PaymentChargeback;
use Equidna\StagHerd\Events\PaymentRefunded;
use Equidna\StagHerd\Events\PaymentRejected;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use Illuminate\Support\Facades\Log;

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
        'DECLINED' => 'Declinado',
        'REFUNDED' => 'Reembolsado',
        'CHARGEBACK' => 'Contracargo',
    ];

    private PaymentHandler $payment_handler;

    /**
     * Creates a new Payment wrapper instance.
     *
     * @param mixed                  $payment_model The underlying storage model.
     * @param PaymentRepository|null $repository    Repository instance (optional).
     */
    public function __construct(
        private $payment_model,
        private ?PaymentRepository $repository = null,
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
     * @param bool $onlyEnabled If true, filters out disabled methods.
     *
     * @return array<string, array<string, mixed>> Configuration array of methods.
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
     * @param int|string $id_payment The payment identifier.
     *
     * @return static The payment instance.
     */
    public static function fromID(int|string $id_payment): static
    {
        return app(PaymentManager::class)->fromID($id_payment);
    }

    /**
     * Factory: Wraps an existing model instance.
     *
     * @param mixed $payment The model instance.
     *
     * @return static The wrapped payment instance.
     */
    public static function fromModel($payment): static
    {
        return new static($payment);
    }

    public function getPaymentLink(): ?string
    {
        return $this->payment_model->link;
    }

    public function getExecutionDate(): ?Carbon
    {
        return !is_null($this->payment_model->dt_executed) ? Carbon::parse($this->payment_model->dt_executed) : $this->payment_model->dt_executed;
    }

    public function getRegistrationDate(): int|string
    {
        return !is_null($this->payment_model->dt_registration) ? Carbon::parse($this->payment_model->dt_registration) : $this->payment_model->dt_registration;
    }

    /**
     * Factory: Retrieves a payment by provider method ID.
     *
     * @param string $method    The payment method key.
     * @param string $method_id The provider's payment ID.
     *
     * @return self The payment instance.
     */
    public static function fromMethodID(string $method, string $method_id): self
    {
        return app(PaymentManager::class)->fromMethodID($method, $method_id);
    }

    /**
     * Factory: Requests a new payment.
     *
     * @param float        $amount      Amount to charge.
     * @param string       $method      Payment method.
     * @param PayableOrder $order       Order context.
     * @param mixed        $method_data Extra data.
     *
     * @return static The new payment instance.
     */
    public static function request(
        float $amount,
        string $method,
        PayableOrder $order,
        mixed $method_data = null,
    ): static {
        return app(PaymentManager::class)->request($amount, $method, $order, $method_data);
    }

    /**
     * Attempts to approve the payment.
     *
     * Delegates to the handler and updates the model if approved.
     *
     * @return PaymentResult Result object.
     */
    public function approvePayment(): PaymentResult
    {
        $result = $this->payment_handler->approvePayment($this->payment_model);

        return $this->applyResult($result);
    }

    /**
     * Attempts to cancel the payment.
     *
     * @throws PaymentDeclinedException If cancellation fails.
     *
     * @return static The updated payment instance.
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

        $this->applyResult($result);

        return $this;
    }

    /**
     * Applies a payment result to the model and dispatches the corresponding event.
     *
     * @param PaymentResult $result
     *
     * @return PaymentResult
     */
    public function applyResult(PaymentResult $result): PaymentResult
    {
        $currentStatus = PaymentStateMachine::normalize((string) ($this->payment_model->status ?? PaymentStatus::PENDING->value))
            ?? PaymentStatus::PENDING;
        $targetStatus = PaymentStateMachine::normalize($result->result);

        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->info('Applying payment result', [
            'payment_id' => $this->getID(),
            'current_status' => $currentStatus->value,
            'result' => $result->result,
            'reason' => $result->reason,
        ]);

        if (is_null($targetStatus)) {
            return $this->blockedTransitionResult(
                currentStatus: $currentStatus,
                requestedStatus: $result->result,
                reason: 'Unknown target status',
            );
        }

        if (!PaymentStateMachine::canTransition($currentStatus, $targetStatus)) {
            return $this->blockedTransitionResult(
                currentStatus: $currentStatus,
                requestedStatus: $targetStatus->value,
                reason: 'Invalid payment state transition',
            );
        }

        if ($targetStatus == PaymentStatus::PENDING) {
            $this->repository->save($this->payment_model);

            return $result;
        }

        if ($currentStatus === $targetStatus) {
            $this->repository->save($this->payment_model);

            return $result;
        }

        $this->payment_model->status = $targetStatus->value;

        if (method_exists($this->payment_model, 'setAttribute') || property_exists($this->payment_model, 'dt_executed')) {
            $this->payment_model->dt_executed = Carbon::now();
        }

        $contextProvider = app(PaymentContextProvider::class);
        $executorUri = $contextProvider->getExecutorUri();
        $executorType = $contextProvider->getExecutorType();

        if (method_exists($this->payment_model, 'setAttribute') || property_exists($this->payment_model, 'uri_executor')) {
            if (!is_null($executorUri)) {
                $this->payment_model->uri_executor = $executorUri;
            }

            if (!is_null($executorType)) {
                $this->payment_model->executor_type = $executorType;
            }
        }

        $this->repository->save($this->payment_model);
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->info('Payment status updated', [
            'payment_id' => $this->getID(),
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
        ]);

        switch ($targetStatus) {
            case PaymentStatus::APPROVED:
                PaymentApproved::dispatch($this);

                break;
            case PaymentStatus::REJECTED:
            case PaymentStatus::DECLINED:
                PaymentRejected::dispatch($this);

                break;
            case PaymentStatus::CANCELED:
                PaymentCanceled::dispatch($this);

                break;
            case PaymentStatus::REFUNDED:
                PaymentRefunded::dispatch($this);

                break;
            case PaymentStatus::CHARGEBACK:
                PaymentChargeback::dispatch($this);

                break;
            case PaymentStatus::PENDING:
                break;
        }

        return $result;
    }

    private function blockedTransitionResult(
        PaymentStatus $currentStatus,
        string $requestedStatus,
        string $reason,
    ): PaymentResult {
        Log::channel(config('stag-herd.audit_log_channel', 'stack'))->warning('Payment state transition blocked', [
            'payment_id' => $this->getID(),
            'current_status' => $currentStatus->value,
            'requested_status' => $requestedStatus,
            'reason' => $reason,
        ]);

        return new PaymentResult(
            error: true,
            result: $currentStatus->value,
            reason: $reason . ': ' . $currentStatus->value . ' -> ' . $requestedStatus,
        );
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

    public function getAmount(): int|string
    {
        return $this->payment_model->amount;
    }

    /**
     * Gets the payment method Enum.
     *
     * @return PaymentMethod
     */
    public function getMethod(): PaymentMethod|string
    {
        $method = PaymentMethod::tryFrom($this->payment_model->method);
        if ($method === null) {
            return $this->payment_model->method;
        }

        return $method;
    }

    //TODO ML: Check if we can add personalized getters
    public function getMethodId(): int|string
    {
        return $this->payment_model->method_id;
    }

    public function getExecutor(): ?string
    {
        $contextProvider = app(PaymentContextProvider::class);
        $executorUri = $contextProvider->getExecutorUri();

        return $executorUri;
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
        return $this->payment_handler::CFDI_PAYMENT_FORM;
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
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->payment_model->{$name} ?? null;
    }
}
