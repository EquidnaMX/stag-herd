<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingLineItemData
{
    public function __construct(
        public string $priceReference,
        public int $quantity = 1,
    ) {
        //
    }

    /** @return array{price: string, quantity: int} */
    public function toArray(): array
    {
        return [
            'price' => $this->priceReference,
            'quantity' => max(1, $this->quantity),
        ];
    }
}
