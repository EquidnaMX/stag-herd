<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Illuminate\Support\Arr;

final class StripeResultMapper
{
    public function __construct(
        private readonly StripeStatusMapper $statusMapper = new StripeStatusMapper(),
    ) {
        //
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapPaymentIntentToResult(
        PaymentRequestData $request,
        array $response,
    ): PaymentResultData {
        return $this->mapPaymentIntentResponseToResult(
            method: $request->method,
            response: $response,
            fallbackAmount: $request->amount,
            fallbackCurrency: $request->currency,
            externalReference: $request->externalReference,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapPaymentIntentResponseToResult(
        string $method,
        array $response,
        ?int $fallbackAmount = null,
        ?string $fallbackCurrency = null,
        ?string $externalReference = null,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(
            Arr::get($response, 'status')
        );

        return new PaymentResultData(
            provider: 'stripe',
            method: $method,

            status: $this->statusMapper->mapPaymentIntent(
                $response
            ),

            providerStatus: $providerStatus,

            references: new ProviderReferencesData(
                providerPaymentId: $this->nullableString(
                    Arr::get($response, 'id')
                ),

                providerOrderId: $this->nullableString(
                    Arr::get(
                        $response,
                        'metadata.external_reference'
                    )
                ),

                providerTransactionId: $this->nullableString(
                    Arr::get(
                        $response,
                        'latest_charge'
                    )
                ),
            ),

            amount: Arr::has($response, 'amount')
                ? (int) Arr::get($response, 'amount')
                : $fallbackAmount,

            currency: strtoupper(
                (string) (
                    Arr::get($response, 'currency')
                    ?? $fallbackCurrency
                    ?? ''
                )
            ),

            nextAction: $this->resolveNextAction(
                $response
            ),

            reason: $this->nullableString(
                Arr::get(
                    $response,
                    'last_payment_error.message'
                ) ?? Arr::get(
                    $response,
                    'cancellation_reason'
                )
            ),

            metadata: array_filter(
                [
                    'external_reference' =>
                    $externalReference
                        ?? Arr::get(
                            $response,
                            'metadata.external_reference'
                        ),

                    'stripe_payment_intent_id' =>
                    Arr::get(
                        $response,
                        'id'
                    ),

                    'stripe_client_secret' =>
                    Arr::get(
                        $response,
                        'client_secret'
                    ),

                    'stripe_latest_charge' =>
                    Arr::get(
                        $response,
                        'latest_charge'
                    ),

                    'stripe_next_action_type' =>
                    Arr::get(
                        $response,
                        'next_action.type'
                    ),

                    'stripe_bank_transfer_hosted_instructions_url' =>
                    Arr::get(
                        $response,
                        'next_action.display_bank_transfer_instructions.hosted_instructions_url'
                    ),

                    'stripe_bank_transfer_reference' =>
                    Arr::get(
                        $response,
                        'next_action.display_bank_transfer_instructions.reference'
                    ),

                    'stripe_bank_transfer_type' =>
                    Arr::get(
                        $response,
                        'next_action.display_bank_transfer_instructions.type'
                    ),

                    'stripe_bank_transfer_instructions' =>
                    Arr::get(
                        $response,
                        'next_action.display_bank_transfer_instructions'
                    ),

                    'stripe_customer_id' =>
                    Arr::get(
                        $response,
                        'customer'
                    ),

                    'stripe_payment_method_id' =>
                    Arr::get(
                        $response,
                        'payment_method'
                    ),
                ],
                fn($value) =>
                $value !== null
                    && $value !== ''
            ),

            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapRefundResponseToResult(
        RefundRequestData $request,
        array $response,
    ): PaymentResultData {
        return new PaymentResultData(
            provider: 'stripe',
            method: (string) ($request->method ?? data_get($request->metadata, 'method', 'card')),
            status: PaymentStatusEnum::REFUNDED,
            providerStatus: $this->nullableString(Arr::get($response, 'status', 'refunded')),
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId
                    ?? data_get($request->metadata, 'provider_payment_id'),
                providerRefundId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: Arr::has($response, 'amount')
                ? (int) Arr::get($response, 'amount')
                : $request->amount,
            currency: strtoupper((string) Arr::get($response, 'currency', '')) ?: null,
            nextAction: NextActionData::none(),
            reason: $request->reason,
            metadata: array_filter([
                'stripe_refund_id' => Arr::get($response, 'id'),
                'stripe_refund_status' => Arr::get($response, 'status'),
                'stripe_charge_id' => Arr::get($response, 'charge'),
            ], fn($value) => $value !== null && $value !== ''),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveNextAction(array $response): NextActionData
    {
        $redirectUrl = Arr::get($response, 'next_action.redirect_to_url.url');

        if ($redirectUrl) {
            return NextActionData::redirect((string) $redirectUrl, [
                'stripe_client_secret' => Arr::get($response, 'client_secret'),
                'stripe_next_action_type' => Arr::get($response, 'next_action.type'),
            ]);
        }

        $bankTransferInstructions = Arr::get(
            $response,
            'next_action.display_bank_transfer_instructions',
            [],
        );

        $hostedInstructionsUrl = Arr::get(
            $bankTransferInstructions,
            'hosted_instructions_url',
        );

        if ($hostedInstructionsUrl) {
            return NextActionData::redirect((string) $hostedInstructionsUrl, array_filter([
                'stripe_client_secret' => Arr::get($response, 'client_secret'),
                'stripe_next_action_type' => Arr::get($response, 'next_action.type'),
                'stripe_bank_transfer_reference' => Arr::get($bankTransferInstructions, 'reference'),
                'stripe_bank_transfer_type' => Arr::get($bankTransferInstructions, 'type'),
                'stripe_bank_transfer_financial_addresses' => Arr::get($bankTransferInstructions, 'financial_addresses'),
            ], fn($value) => $value !== null && $value !== ''));
        }

        return NextActionData::none();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
