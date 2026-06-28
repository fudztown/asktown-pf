<?php
declare(strict_types=1);

namespace Asktown\Domain\Provider;

/**
 * Standardized Financial Transaction interface for asktown-pf
 */
class StandardTransaction
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $date, // YYYY-MM-DD
        public string $description,
        public float $amount,
        public string $currency,
        public bool $isPending,
        public string $provider // 'truelayer', 'plaid'
    ) {}

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->id,
            'account_id'     => $this->accountId,
            'date'           => $this->date,
            'description'    => $this->description,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'is_pending'     => $this->isPending,
            'provider'       => $this->provider
        ];
    }
}
