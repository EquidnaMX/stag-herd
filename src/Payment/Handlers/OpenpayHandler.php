<?php

/**
 * Payment handler for Openpay transactions.
 *
 * Manages Openpay payment requests, validations, and bank charge operations.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Adapters\OpenPayAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Exception;
use Illuminate\Http\Request;
use Throwable;

class OpenpayHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::OPENPAY->value;

    public const CFDI_PAYMENT_FORM = '03';

    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
        private ?OpenPayAdapter $openpay_adapter = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->openpay_adapter ??= new OpenPayAdapter();
    }

    public function requestPayment(): PaymentResult
    {
        // Don't call parent

        $methodId = null;
        $link = null;
        $result = 'PENDING';
        $reason = 'Always PENDING';

        try {
            $payment_details = $this->openpay_adapter->createBankCharge(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : ''),
                $this->order ? $this->order->getClient()->getName() : '',
                $this->order ? $this->order->getClient()->getEmail() : '',
            );

            $methodId = $payment_details->id ?? null;
            $link = $payment_details->payment_method->url ?? null;
        } catch (Exception $e) {
            $result = 'DECLINED';
            $reason = $e->getMessage();
        }

        if ($result == 'DECLINED') {
            return PaymentResult::declined($reason);
        }

        return PaymentResult::pending(
            method_id: (string) $methodId,
            link: $link,
            reason: 'Openpay pending',
        );
    }

    protected function validatePayment($paymentModel): PaymentResult
    {
        try {
            $openpay_result = $this->openpay_adapter->getChargeDetails((string) $paymentModel->method_id);

            $amountValue = $openpay_result->amount ?? null;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $openpay_result->status ?? null;

            if ($status == 'completed') {
                return PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id,
                );
            }

            return PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'Openpay Status: ' . $status,
            );
        } catch (Exception $e) {
            return PaymentResult::declined($e->getMessage());
        }
    }

    public function cancelPayment($paymentModel): PaymentResult
    {
        try {
            $this->openpay_adapter->getRefund(
                $paymentModel->method_id,
                $paymentModel->amount,
            );

            return PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies Openpay webhook signature.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $secret = (string) config('stag-herd.openpay.webhook_secret');

        return WebhookVerifier::verifyOpenpaySignature($request, $secret);
    }

    /**
     * Processes validated Openpay webhook event.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');

        $candidates = [
            data_get($payload, 'transaction.id'),
            data_get($payload, 'transaction.charge_id'),
            data_get($payload, 'transaction_id'),
            data_get($payload, 'id'),
        ];

        $payment = self::resolvePayment($candidates);
        if (is_null($payment)) {
            return;
        }

        $result = match ($type) {
            'charge.succeeded' => PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $payment->getMethodId(),
            ),
            'charge.failed' => new PaymentResult(
                error: true,
                result: PaymentStatus::REJECTED->value,
                reason: 'Openpay charge failed',
            ),
            'charge.cancelled' => PaymentResult::canceled('Openpay charge canceled'),
            'charge.refunded' => PaymentResult::refunded('Openpay charge refunded'),
            'charge.created',
            'charge.pending' => PaymentResult::pending(
                method_id: (string) $payment->getMethodId(),
                reason: 'Openpay charge pending',
            ),
            'chargeback.created',
            'chargeback.updated',
            'chargeback.accepted',
            'chargeback.rejected' => PaymentResult::chargeback('Openpay chargeback'),
            default => null,
        };

        if ($result) {
            $payment->applyResult($result);
        }
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
                return Payment::fromMethodID('OPENPAY', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
