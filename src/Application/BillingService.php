<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Contracts\BillingCatalogProvider;
use Equidna\StagHerd\Contracts\BillingResourceRepository;
use Equidna\StagHerd\Contracts\CreatesCustomerPortal;
use Equidna\StagHerd\Contracts\HostedCheckoutProvider;
use Equidna\StagHerd\Contracts\ManagesSubscriptions;
use Equidna\StagHerd\Data\BillingPortalRequestData;
use Equidna\StagHerd\Data\BillingPortalSessionData;
use Equidna\StagHerd\Data\BillingPriceData;
use Equidna\StagHerd\Data\BillingProductData;
use Equidna\StagHerd\Data\CheckoutLookupData;
use Equidna\StagHerd\Data\CheckoutRequestData;
use Equidna\StagHerd\Data\CheckoutSessionData;
use Equidna\StagHerd\Data\SubscriptionCancellationData;
use Equidna\StagHerd\Data\SubscriptionData;
use Equidna\StagHerd\Data\SubscriptionLookupData;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\BillingProviderRegistry;
use Equidna\StagHerd\Support\CredentialContextManager;

final readonly class BillingService
{
    public function __construct(
        private BillingProviderRegistry $providers,
        private CredentialContextManager $credentials,
        private BillingResourceRepository $resources,
    ) {
        //
    }

    public function createCheckout(CheckoutRequestData $request): CheckoutSessionData
    {
        $provider = $this->providers->get($request->provider);
        if (!$provider instanceof HostedCheckoutProvider) {
            $this->unsupported($provider, 'hosted checkout');
        }

        $result = $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): CheckoutSessionData => $provider->createCheckout($request),
        );
        $this->storeCheckout($result);

        return $result;
    }

    public function lookupCheckout(CheckoutLookupData $request): CheckoutSessionData
    {
        $provider = $this->providers->get($request->provider);
        if (!$provider instanceof HostedCheckoutProvider) {
            $this->unsupported($provider, 'checkout lookup');
        }

        $result = $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): CheckoutSessionData => $provider->lookupCheckout($request),
        );
        $this->storeCheckout($result);

        return $result;
    }

    public function lookupSubscription(SubscriptionLookupData $request): SubscriptionData
    {
        $provider = $this->providers->get($request->provider);
        if (!$provider instanceof ManagesSubscriptions) {
            $this->unsupported($provider, 'subscription lookup');
        }

        $result = $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): SubscriptionData => $provider->lookupSubscription($request),
        );
        $this->storeSubscription($result);

        return $result;
    }

    public function cancelSubscription(SubscriptionCancellationData $request): SubscriptionData
    {
        $provider = $this->providers->get($request->provider);
        if (!$provider instanceof ManagesSubscriptions) {
            $this->unsupported($provider, 'subscription cancellation');
        }

        $result = $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): SubscriptionData => $provider->cancelSubscription($request),
        );
        $this->storeSubscription($result);

        return $result;
    }

    public function createBillingPortal(BillingPortalRequestData $request): BillingPortalSessionData
    {
        $provider = $this->providers->get($request->provider);
        if (!$provider instanceof CreatesCustomerPortal) {
            $this->unsupported($provider, 'customer portal');
        }

        return $this->credentials->run(
            $request->provider,
            $request->credentialContext,
            fn (): BillingPortalSessionData => $provider->createBillingPortal($request),
        );
    }

    /** @param array<string, scalar|null> $metadata */
    public function createProduct(
        string $providerName,
        string $credentialContext,
        string $name,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): BillingProductData {
        $provider = $this->providers->get($providerName);
        if (!$provider instanceof BillingCatalogProvider) {
            $this->unsupported($provider, 'product creation');
        }

        return $this->credentials->run(
            $providerName,
            $credentialContext,
            fn (): BillingProductData => $provider->createProduct(
                $credentialContext,
                $name,
                $metadata,
                $idempotencyKey,
            ),
        );
    }

    public function createPrice(
        string $providerName,
        string $credentialContext,
        string $productId,
        int $unitAmount,
        string $currency,
        ?string $recurringInterval = null,
        ?string $idempotencyKey = null,
    ): BillingPriceData {
        $provider = $this->providers->get($providerName);
        if (!$provider instanceof BillingCatalogProvider) {
            $this->unsupported($provider, 'price creation');
        }

        return $this->credentials->run(
            $providerName,
            $credentialContext,
            fn (): BillingPriceData => $provider->createPrice(
                $credentialContext,
                $productId,
                $unitAmount,
                $currency,
                $recurringInterval,
                $idempotencyKey,
            ),
        );
    }

    private function storeCheckout(CheckoutSessionData $checkout): void
    {
        $this->resources->upsert(
            $checkout->provider,
            $checkout->credentialContext,
            'checkout_session',
            $checkout->id,
            $checkout->status->value,
            $checkout->rawPayload,
        );
    }

    private function storeSubscription(SubscriptionData $subscription): void
    {
        $this->resources->upsert(
            $subscription->provider,
            $subscription->credentialContext,
            'subscription',
            $subscription->id,
            $subscription->status->value,
            $subscription->rawPayload,
        );
    }

    private function unsupported(object $provider, string $operation): never
    {
        throw UnsupportedOperationException::forOperation(
            $operation,
            sprintf('Provider [%s] does not support %s.', $provider->getName(), $operation),
        );
    }
}
