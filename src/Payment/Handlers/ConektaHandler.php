<?php

/**
 * Payment handler for Conekta (OXXO) transactions.
 *
 * Manages Conekta payment requests and validations using the ConektaAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Contracts\ConektaGateway;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Exception;
use Illuminate\Http\Request;
use Throwable;

class ConektaHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::CONEKTA->value;

    public const CFDI_PAYMENT_FORM = '01';

    private ConektaGateway $conekta_adapter;

    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
        ?ConektaGateway $conekta_adapter = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->conekta_adapter = $conekta_adapter ?? app(ConektaGateway::class);
    }

    public function requestPayment(): PaymentResult
    {
        // Don't call parent

        $methodId = null;
        $link = null;
        $result = 'PENDING';
        $reason = 'Always PENDING';

        try {
            $methodType = (string) ($this->getMethodData('payment_method') ?? 'oxxo_cash');
            if ($methodType === 'oxxo') {
                $methodType = 'oxxo_cash';
            }

            $tokenId = $this->getMethodData('token');

            $payment_details = $this->conekta_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : ''),
                $this->getMethodData('payer_email') ?? ($this->order ? $this->order->getClient()->getEmail() : ''),
                $this->getMethodData('payer_name') ?? ($this->order ? $this->order->getClient()->getName() : null),
                [
                    'order_id' => $this->order ? (string) $this->order->getID() : null,
                ],
                $methodType,
                $tokenId,
            );

            $methodId = $payment_details->id ?? null;
            $link = $payment_details->payment_url ?? null;
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
            reason: 'Conekta pending',
        );
    }

    protected function validatePayment($paymentModel): PaymentResult
    {
        try {
            $conekta_result = $this->conekta_adapter->getOrderDetails((string) $paymentModel->method_id);

            $amountRaw = $conekta_result->amount ?? null;
            if (!is_null($amountRaw)) {
                $amountValue = ((float) $amountRaw) / 100;
                if (abs($amountValue - $paymentModel->amount) > 0.01) {
                    throw new Exception('Invalid amount!');
                }
            }

            $status = $conekta_result->payment_status
                ?? data_get($conekta_result, 'charges.data.0.status')
                ?? null;

            return match ($status) {
                'paid' => PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id,
                ),
                'pending_payment', 'pending' => PaymentResult::pending(
                    method_id: (string) $paymentModel->method_id,
                    reason: 'Conekta Status: ' . $status,
                ),
                'expired', 'canceled' => PaymentResult::canceled('Conekta canceled'),
                'declined' => PaymentResult::declined(
                    reason: 'Conekta Status: ' . $status,
                ),
                default => PaymentResult::pending(
                    method_id: (string) $paymentModel->method_id,
                    reason: 'Conekta Status: ' . ($status ?? 'unknown'),
                ),
            };
        } catch (Exception $e) {
            return PaymentResult::declined(
                reason: $e->getMessage(),
            );
        }
    }

    public function cancelPayment($paymentModel): PaymentResult
    {
        throw new PaymentDeclinedException('Conekta payments cannot be cancelled');
    }

    /**
     * Verifies Conekta webhook signature.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        return WebhookVerifier::verifyConektaSignature($request);
    }

    /**
     * Processes validated Conekta webhook event.
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
            data_get($payload, 'data.object.id'),
            data_get($payload, 'data.object.order_id'),
            data_get($payload, 'data.object.charge_id'),
        ];

        $payment = self::resolvePayment($candidates);
        if (is_null($payment)) {
            return;
        }

        $result = match ($type) {
            'order.paid',
            'charge.paid' => PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $payment->getMethodId(),
            ),
            'order.pending_payment',
            'charge.pending_payment' => PaymentResult::pending(
                method_id: (string) $payment->getMethodId(),
                reason: 'Conekta pending payment',
            ),
            'order.declined' => new PaymentResult(
                error: true,
                result: PaymentStatus::REJECTED->value,
                reason: 'Conekta order declined',
            ),
            'order.canceled',
            'order.expired',
            'charge.canceled' => PaymentResult::canceled('Conekta payment canceled'),
            'charge.refunded',
            'order.refunded',
            'order.partially_refunded' => PaymentResult::refunded('Conekta refunded'),
            'charge.chargeback.created',
            'charge.chargeback.updated',
            'charge.chargeback.fraudulent',
            'charge.chargeback.won' => PaymentResult::chargeback('Conekta chargeback'),
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
                return Payment::fromMethodID('CONEKTA', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
