<?php

namespace Equidna\StagHerd\Http\Controllers;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

class MercadoPagoController extends Controller
{
    public function processBrick(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'provider' => ['nullable', 'string'],
                'method' => ['nullable', 'string'],

                'amount' => ['required', 'numeric', 'min:0.01'],
                'currency' => ['required', 'string', 'size:3'],
                'external_reference' => ['nullable', 'string', 'max:255'],
                'payer_email' => ['nullable', 'email', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],

                'idempotency_key' => ['nullable', 'string', 'max:64'],
                'device_id' => ['nullable', 'string', 'max:255'],

                'metadata' => ['nullable', 'array'],

                'mercado_pago' => ['nullable', 'array'],
                'mercado_pago.token' => ['nullable', 'string'],
                'mercado_pago.payment_method_id' => ['nullable', 'string'],
                'mercado_pago.issuer_id' => ['nullable'],
                'mercado_pago.installments' => ['nullable'],
                'mercado_pago.payer' => ['nullable', 'array'],
                'mercado_pago.idempotency_key' => ['nullable', 'string', 'max:64'],
                'mercado_pago.device_id' => ['nullable', 'string', 'max:255'],

                'token' => ['nullable', 'string'],
                'payment_method_id' => ['nullable', 'string'],
                'issuer_id' => ['nullable'],
                'installments' => ['nullable'],
                'payer' => ['nullable', 'array'],

                'raw_form_data' => ['nullable', 'array'],
            ]);

            $mercadoPagoData = $data['mercado_pago'] ?? [];

            $token = data_get($mercadoPagoData, 'token')
                ?? ($data['token'] ?? null);

            $paymentMethodId = data_get($mercadoPagoData, 'payment_method_id')
                ?? ($data['payment_method_id'] ?? null);

            $issuerId = data_get($mercadoPagoData, 'issuer_id')
                ?? ($data['issuer_id'] ?? null);

            $installments = data_get($mercadoPagoData, 'installments')
                ?? ($data['installments'] ?? 1);

            $payerFromMercadoPago = data_get($mercadoPagoData, 'payer', []);
            $payerFromRoot = $data['payer'] ?? [];

            $payerEmail = $data['payer_email']
                ?? data_get($payerFromMercadoPago, 'email')
                ?? data_get($payerFromRoot, 'email')
                ?? data_get($data, 'raw_form_data.payer.email')
                ?? 'cliente@test.com';

            if (! $token) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió token de Mercado Pago.',
                    'received' => $data,
                ], 422);
            }

            if (! $paymentMethodId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El Brick no envió payment_method_id.',
                    'received' => $data,
                ], 422);
            }

            $externalReference = $data['external_reference']
                ?? 'BRICK-' . now()->format('YmdHis');

            $idempotencyKey = substr(
                (string) (
                    data_get($mercadoPagoData, 'idempotency_key')
                    ?? $data['idempotency_key']
                    ?? $request->header('X-Idempotency-Key')
                    ?? Str::uuid()
                ),
                0,
                64,
            );

            $deviceId = data_get($mercadoPagoData, 'device_id')
                ?? $data['device_id']
                ?? null;

            $metadata = $this->cleanMetadata($data['metadata'] ?? []);

            $metadata = array_replace_recursive($metadata, [
                'source' => 'stag-herd-brick-host-ui',
                'external_reference' => $externalReference,
                'mercado_pago' => array_filter([
                    'token' => $token,
                    'payment_method_id' => $paymentMethodId,
                    'issuer_id' => $issuerId,
                    'installments' => (int) $installments,
                    'payer' => array_merge(
                        is_array($payerFromMercadoPago) ? $payerFromMercadoPago : [],
                        is_array($payerFromRoot) ? $payerFromRoot : [],
                        ['email' => $payerEmail],
                    ),
                    'idempotency_key' => $idempotencyKey,
                    'device_id' => $deviceId,
                ], fn($value) => $value !== null && $value !== ''),
                'raw_form_data' => $data['raw_form_data'] ?? null,
            ]);

            $metadata = $this->cleanMetadata($metadata);

            $payment = StagHerd::createPayment(new PaymentRequestData(
                amount: MoneyFormatter::fromDecimal($data['amount']),
                currency: strtoupper($data['currency']),
                method: strtolower($data['method'] ?? 'card'),
                provider: strtolower($data['provider'] ?? 'mercado_pago'),
                externalReference: $externalReference,
                payerReference: null,
                payerEmail: $payerEmail,
                description: $data['description'] ?? 'Payment from Mercado Pago Brick',
                metadata: $metadata,
            ));

            return response()->json([
                'ok' => true,
                'message' => 'Pago creado correctamente.',
                'payment' => $payment->toArray(),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'type' => class_basename($exception),
                'message' => $exception->getMessage(),
                'file' => config('app.debug') ? $exception->getFile() : null,
                'line' => config('app.debug') ? $exception->getLine() : null,
            ], 422);
        }
    }

    private function cleanMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->cleanMetadata($value);

                if ($nested === []) {
                    continue;
                }

                $clean[$key] = $nested;

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
