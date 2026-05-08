<?php

/**
 * Abstract base Payment Handler.
 *
 * Defines the contract and common behavior for all payment handlers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Base class for payment provider implementations.
 */
abstract class PaymentHandler
{
    public const PAYMENT_METHOD = 'BASE';

    public const CFDI = null;

    public const CFDI_PAYMENT_FORM = '01';

    public const ALLOW_DUPLICATED_METHOD_ID = false;

    /**
     * Creates a new PaymentHandler instance.
     *
     * @param float             $amount      Payment amount.
     * @param PayableOrder|null $order       Order context.
     * @param PaymentData|null  $method_data Method specific data.
     */
    public function __construct(
        protected float $amount,
        protected ?PayableOrder $order,
        protected ?PaymentData $method_data,
    ) {
        //
    }

    /**
     * Initiates the payment request logic.
     *
     * @throws PaymentDeclinedException If the order is missing.
     *
     * @return PaymentResult Result object with status and ID.
     */
    public function requestPayment(): PaymentResult
    {
        if (is_null($this->order)) {
            throw new PaymentDeclinedException('Order not loaded');
        }

        // $client = $this->order->getClient();

        $methodId = $this->getMethodData('payment_method_id');

        return PaymentResult::pending(
            method_id: $methodId ?? Str::random(20),
            reason: 'Always PENDING',
        );
    }

    /**
     * Validates payment status and details.
     *
     * @param object $paymentModel The payment model or wrapper.
     *
     * @throws InvalidPaymentMethodException If method mismatch.
     * @throws PaymentDeclinedException      If validation fails.
     *
     * @return PaymentResult Validation result.
     */
    protected function validatePayment(object $paymentModel): PaymentResult
    {
        // Legacy no-op check removed: VALID_STATUS holds statuses, not methods.

        if ($paymentModel->amount != $this->amount) {
            throw new PaymentDeclinedException('Invalid amount');
        }

        if (is_null($this->order)) {
            throw new PaymentDeclinedException('Order not loaded');
        }

        if ($paymentModel->status != 'PENDING') {
            throw new PaymentDeclinedException('Payment is not pending validation');
        }

        return PaymentResult::pending(
            method_id: $paymentModel->method_id,
            reason: 'Always PENDING',
        );
    }

    /**
     * Approves the payment.
     *
     * @param object $paymentModel The payment model.
     *
     * @return PaymentResult Approval result.
     */
    public function approvePayment(object $paymentModel): PaymentResult
    {
        return $this->validatePayment($paymentModel);
    }

    /**
     * Cancels the payment.
     *
     * @param object $paymentModel The payment model.
     *
     * @return PaymentResult Cancellation result.
     */
    public function cancelPayment(object $paymentModel): PaymentResult
    {
        return PaymentResult::canceled();
    }

    /**
     * Calculates the payment fee.
     *
     * @return float The calculated fee.
     */
    public function getFee(): float
    {
        $methodConfig = config(
            'stag-herd.methods.' . static::PAYMENT_METHOD,
            config('stag-herd.custom_methods.' . static::PAYMENT_METHOD, []),
        );
        $feeConfig = is_array($methodConfig) ? ($methodConfig['fee'] ?? []) : [];

        $fixed = is_array($feeConfig) ? ($feeConfig['fixed'] ?? 0) : 0;
        $variable = is_array($feeConfig) ? ($feeConfig['variable'] ?? 0) : 0;

        return $fixed + ($this->amount * $variable);
    }

    /**
     * Gets the effective date of the payment.
     *
     * @return Carbon Effective date.
     */
    public function getEffectiveDate(): Carbon
    {
        $effective_date = $this->getMethodData('effective_date');

        return $effective_date ? Carbon::parse($effective_date) : Carbon::now();
    }

    /**
     * Retrieves a value from method data or extra payload.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function getMethodData(string $key, mixed $default = null): mixed
    {
        if (!($this->method_data instanceof PaymentData)) {
            return $default;
        }

        return $this->method_data->get($key, $default);
    }

    /**
     * Checks if a value exists in method data or extra payload.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function hasMethodData(string $key): bool
    {
        if (!($this->method_data instanceof PaymentData)) {
            return false;
        }

        return $this->method_data->has($key);
    }

    /**
     * Verifies the webhook signature.
     *
     * Static method to allow verification before instantiation.
     *
     * @param Request $request The incoming request.
     *
     * @return array{valid: bool, eventId: string|null, reason: string|null, data: mixed} Verification result.
     */
    public static function verifyWebhook(Request $request): array
    {
        return [
            'valid' => false,
            'reason' => 'Not implemented',
            'eventId' => null,
            'data' => null,
        ];
    }
}
