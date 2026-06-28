<?php
declare(strict_types=1);

namespace Asktown\Domain\Provider;

/**
 * Standardized Financial Account interface for asktown-pf
 */
class StandardAccount
{
    public function __construct(
        public string $id,
        public string $displayName,
        public float $balance,
        public string $currency,
        public string $type, // 'CASH', 'CREDIT', 'INVESTMENT'
        public string $provider // 'truelayer', 'plaid'
    ) {}

    public function toArray(): array
    {
        return [
            'account_id'   => $this->id,
            'display_name' => $this->displayName,
            'balance'      => ['current' => $this->balance],
            'currency'     => $this->currency,
            'account_type' => $this->type,
            'provider'     => ['provider_id' => $this->provider]
        ];
    }
}
