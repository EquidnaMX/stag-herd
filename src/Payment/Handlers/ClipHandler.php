<?php

/**
 * Payment handler for Clip transactions.
 *
 * Manages Clip payment requests, validations, and refunds using the ClipAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Adapters\ClipAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ClipHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::CLIP->value;

    public const CFDI_PAYMENT_FORM = '04';

    private ClipAdapter $clip_adapter;

    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->clip_adapter = new ClipAdapter();
    }

    public function requestPayment(): PaymentResult
    {
        // Don't call parent

        $methodId = $this->getMethodData('payment_method_id');

        $link = null;
        $payment_status = null;
        $reason = null;

        try {
            if ($methodId) {
                $payment_details = $this->clip_adapter->getPaymentDetails((string) $methodId);
                $payment_status = $payment_details->status ?? null;
            } else {
                throw new RuntimeException('No payment method id available');
            }
        } catch (Exception $e) {
            $payment_details = $this->clip_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : ''),
                [
                    'currency' => $this->getMethodData('currency', config('stag-herd.clip.currency', 'MXN')),
                    'redirection_url' => $this->buildRedirectionUrl(),
                    'metadata' => $this->buildMetadata(),
                    'override_settings' => $this->getMethodData('override_settings'),
                    'webhook_url' => $this->getMethodData('webhook_url') ?? config('stag-herd.clip.webhook_url') ?? route('stag-herd.clip'),
                ],
            );

            $link = $payment_details->payment_request_url ?? $payment_details->payment_url ?? null;
            $methodId = $payment_details->payment_request_id ?? $payment_details->id ?? null;
            $payment_status = $payment_details->status ?? $payment_details->resource_status ?? null;
        }

        $resultStatus = match ($payment_status) {
            'CREATED', 'PENDING', 'created', 'pending' => 'PENDING',
            'COMPLETED', 'completed', 'paid' => 'APPROVED',
            default => 'DECLINED',
        };

        if ($resultStatus == 'DECLINED') {
            return PaymentResult::declined(
                reason: 'Clip status: ' . ($payment_status ?? 'Unknown'),
            );
        }

        if ($resultStatus === 'APPROVED') {
            return PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $methodId,
                link: $link,
            );
        }

        return PaymentResult::pending(
            method_id: (string) $methodId,
            link: $link,
            reason: 'Clip status: ' . ($payment_status ?? 'pending'),
        );
    }

    protected function validatePayment($paymentModel): PaymentResult
    {
        try {
            $clip_result = $this->clip_adapter->getPaymentDetails((string) $paymentModel->method_id);

            $amountValue = $clip_result->amount ?? null;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = strtoupper((string) ($clip_result->resource_status ?? $clip_result->status ?? ''));

            if ($status === 'COMPLETED') {
                return PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id,
                );
            }

            return PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'Clip Status: ' . $status,
            );
        } catch (Exception $e) {
            return PaymentResult::declined(
                reason: $e->getMessage(),
            );
        }
    }

    public function cancelPayment($paymentModel): PaymentResult
    {
        try {
            $this->clip_adapter->getRefund(
                $paymentModel->method_id,
                $paymentModel->amount,
            );

            return PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies Clip webhook payload.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $payload = $request->json()->all();
        $paymentRequestId = self::extractPaymentRequestId($payload);
        if (!$paymentRequestId) {
            return ['valid' => false, 'reason' => 'Missing Clip payment_request_id'];
        }

        $receiptNo = data_get($payload, 'receipt_no') ?? data_get($payload, 'data.receipt_no');
        $status = data_get($payload, 'resource_status') ?? data_get($payload, 'data.resource_status');
        $eventId = implode(':', array_filter([(string) $paymentRequestId, (string) $receiptNo, (string) $status]));

        return ['valid' => true, 'eventId' => $eventId];
    }

    /**
     * Processes validated Clip webhook event.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $payload = $request->json()->all();
        $paymentRequestId = self::extractPaymentRequestId($payload);
        if (!$paymentRequestId) {
            return;
        }

        $candidates = [
            $paymentRequestId,
        ];

        $payment = self::resolvePayment($candidates);
        if (is_null($payment)) {
            return;
        }

        $details = (new ClipAdapter())->getPaymentDetails($paymentRequestId);
        self::storeClipDetails($payment, $details, $payload);

        $status = strtoupper((string) (
            data_get($details, 'resource_status')
            ?? data_get($details, 'status')
            ?? data_get($details, 'last_status_message')
            ?? ''
        ));

        $result = match ($status) {
            'COMPLETED' => PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $payment->getMethodId(),
            ),
            'CREATED',
            'PENDING' => PaymentResult::pending(
                method_id: (string) $payment->getMethodId(),
                reason: 'Clip payment pending',
            ),
            'CANCELED',
            'CANCELLED',
            'EXPIRED' => PaymentResult::canceled('Clip payment canceled'),
            'DECLINED' => new PaymentResult(
                error: true,
                result: PaymentStatus::REJECTED->value,
                reason: 'Clip payment declined',
            ),
            default => null,
        };

        if ($result) {
            $payment->applyResult($result);
        }
    }

    /**
     * @return array{success: string, error: string, default: string}
     */
    private function buildRedirectionUrl(): array
    {
        $success = $this->getMethodData('success_url') ?? config('stag-herd.clip.success_url') ?? url('/');
        $error = $this->getMethodData('error_url') ?? config('stag-herd.clip.error_url') ?? $success;
        $default = $this->getMethodData('default_url') ?? config('stag-herd.clip.default_url') ?? $success;

        return [
            'success' => (string) $success,
            'error' => (string) $error,
            'default' => (string) $default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(): array
    {
        $metadata = $this->getMethodData('metadata', []);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        if ($this->order) {
            $metadata['me_reference_id'] ??= (string) $this->order->getID();
            $metadata['customer_info'] ??= [
                'name' => $this->order->getClient()->getName(),
                'email' => $this->order->getClient()->getEmail(),
            ];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractPaymentRequestId(array $payload): ?string
    {
        $id = data_get($payload, 'payment_request_id')
            ?? data_get($payload, 'data.payment_request_id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'data.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function storeClipDetails(Payment $payment, object $details, array $payload): void
    {
        $model = $payment->getPaymentModel();
        $methodData = json_decode($model->method_data ?? '', true);
        if (!is_array($methodData)) {
            $methodData = [];
        }

        foreach (['payment_request_id', 'receipt_no', 'resource_status', 'status'] as $key) {
            $value = data_get($details, $key) ?? data_get($payload, $key) ?? data_get($payload, 'data.' . $key);
            if (is_scalar($value) && $value !== '') {
                $methodData[$key] = (string) $value;
            }
        }

        $model->method_data = json_encode($methodData);
    }

    /**
     * Attempts to resolve a payment using a list of candidate method IDs.
     *
     * @param array<int, mixed> $candidates
     *
     * @return Payment|null
     */
    private static function resolvePayment(array $candidates): ?Payment
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            try {
                return Payment::fromMethodID('CLIP', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
