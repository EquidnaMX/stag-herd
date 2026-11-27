<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Data\PaymentRequestData;

final class PlatformFeeResolver
{
    public function resolve(string $provider, PaymentRequestData $request): ?int
    {
        $provider = strtolower($provider);

        if (! $this->hasPlatformContext($provider, $request)) {
            return null;
        }

        $percentage = config("stag-herd.providers.{$provider}.platform_fee.percentage");

        if (is_numeric($percentage) && (float) $percentage > 0) {
            return max(0, (int) round($request->amount * ((float) $percentage / 100)));
        }

        return $request->platformContext->platformFeeAmount !== null
            && $request->platformContext->platformFeeAmount > 0
            ? $request->platformContext->platformFeeAmount
            : null;
    }

    private function hasPlatformContext(string $provider, PaymentRequestData $request): bool
    {
        return match ($provider) {
            'paypal' => filled($request->platformContext->paypalSellerMerchantId()),
            'stripe' => filled($request->platformContext->stripeDestinationAccount()),
            'mercado_pago' => filled($request->platformContext->mercadoPagoSellerAccessToken()),
            default => false,
        };
    }
}
