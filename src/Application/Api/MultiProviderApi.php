<?php
declare(strict_types=1);

namespace Asktown\Application\Api;

use Asktown\Infrastructure\Bank\TrueLayerService;
use Asktown\Infrastructure\Investments\PlaidService;
use Asktown\Domain\Provider\StandardAccount;
use Asktown\Domain\Provider\StandardTransaction;

/**
 * Aggregator API to fetch and normalize data from multiple providers.
 */
class MultiProviderApi
{
    public function __construct(
        private TrueLayerService $trueLayer,
        private PlaidService $plaid,
        private \PDO $pdo
    ) {}

    /**
     * @return StandardAccount[]
     */
    public function fetchAllAccounts(string $userId): array
    {
        $all = [];

        // 1. Fetch from TrueLayer
        $tlAccounts = $this->trueLayer->getAccounts($userId, $this->pdo);
        foreach ($tlAccounts as $acc) {
            $all[] = new StandardAccount(
                id:          $acc['account_id'],
                displayName: $acc['display_name'],
                balance:     (float)$acc['balance']['current'],
                currency:    $acc['currency'],
                type:        ($acc['provider']['provider_id'] === 'ob-amex') ? 'CREDIT_CARD' : 'ACCOUNT',
                provider:    'truelayer'
            );
        }

        // 2. Fetch from Plaid (Issue #21)
        $plaidAccounts = $this->plaid->getAccounts($userId, $this->pdo);
        foreach ($plaidAccounts as $acc) {
            $all[] = new StandardAccount(
                id:          $acc['account_id'],
                displayName: $acc['name'],
                balance:     (float)($acc['balances']['current'] ?? 0),
                currency:    $acc['balances']['iso_currency_code'] ?? 'GBP',
                type:        'INVESTMENT',
                provider:    'plaid'
            );
        }

        return $all;
    }

    /**
     * @return StandardTransaction[]
     */
    public function fetchAllTransactions(string $userId): array
    {
        $all = [];

        // 1. TrueLayer
        $tlTxs = $this->trueLayer->getTransactions($userId, $this->pdo);
        foreach ($tlTxs as $tx) {
            $all[] = new StandardTransaction(
                id:          $tx['id'],
                accountId:   $tx['account_id'],
                date:        $tx['date'],
                description: $tx['description'],
                amount:      $tx['amount'],
                currency:    $tx['currency'],
                isPending:   $tx['is_pending'],
                provider:    'truelayer'
            );
        }

        // 2. Plaid
        $plTxs = $this->plaid->getTransactions($userId, $this->pdo);
        foreach ($plTxs as $tx) {
            $all[] = new StandardTransaction(
                id:          $tx['id'],
                accountId:   $tx['account_id'],
                date:        $tx['date'],
                description: $tx['description'],
                amount:      $tx['amount'],
                currency:    $tx['currency'],
                isPending:   $tx['is_pending'],
                provider:    'plaid'
            );
        }

        // Global Sorting: Newest First
        usort($all, fn($a, $b) => strcmp($b->date, $a->date));

        return $all;
    }
}
