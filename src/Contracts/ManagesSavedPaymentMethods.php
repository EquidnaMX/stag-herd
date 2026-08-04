<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\SavedPaymentMethodData;
use Equidna\StagHerd\Data\SavedPaymentMethodLookupData;
use Equidna\StagHerd\Data\SavedPaymentMethodUpsertData;

interface ManagesSavedPaymentMethods
{
    public function upsert(SavedPaymentMethodUpsertData $request): SavedPaymentMethodData;

    /**
     * @return array<int, SavedPaymentMethodData>
     */
    public function listActive(SavedPaymentMethodLookupData $request): array;

    public function markDefault(SavedPaymentMethodLookupData $request): SavedPaymentMethodData;

    public function deactivate(SavedPaymentMethodLookupData $request): SavedPaymentMethodData;

    public function resolveUsable(SavedPaymentMethodLookupData $request): SavedPaymentMethodData;
}
