<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ExtractsPaymentMethodFromPayment;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class RegisterPaymentMethodFromResult
{
    public function __construct(
        private ProviderRegistry $providers,
        private RegisterPaymentMethod $registerPaymentMethod,
    ) {
        //
    }

    public function handle(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): ?PaymentMethodData {
        if (! $this->shouldRegister($request, $result)) {
            return null;
        }

        $provider = $this->providers->get($result->provider);

        if (! $provider instanceof ExtractsPaymentMethodFromPayment) {
            return null;
        }

        $paymentMethod = $provider->paymentMethodFromPayment($request, $result);

        if ($paymentMethod === null) {
            return null;
        }

        return $this->registerPaymentMethod->handle($paymentMethod);
    }

    private function shouldRegister(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): bool {
        $registerPaymentMethod = data_get(
            $request->metadata,
            'stag_herd.register_payment_method',
        );

        if ($registerPaymentMethod !== null) {
            return filter_var($registerPaymentMethod, FILTER_VALIDATE_BOOL);
        }

        return filter_var(
            data_get($request->metadata, 'save_payment_method')
                ?? data_get($request->metadata, 'stripe.save_payment_method')
                ?? data_get($request->metadata, 'method_data.metadata.save_payment_method')
                ?? data_get($result->metadata, 'save_payment_method')
                ?? data_get($result->rawPayload, 'metadata.save_payment_method')
                ?? false,
            FILTER_VALIDATE_BOOL,
        );
    }
}
