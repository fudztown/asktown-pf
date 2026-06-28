<?php
declare(strict_types=1);

namespace Asktown\Application;

use Asktown\Infrastructure\Auth\SupabaseAuthService;
use Asktown\Infrastructure\Bank\TrueLayerService;

class DashboardPresenter
{
    private SupabaseAuthService $auth;
    private TrueLayerService $bank;

    public function __construct(SupabaseAuthService $auth, TrueLayerService $bank)
    {
        $this->auth = $auth;
        $this->bank = $bank;
    }

    public function getDashboardData(string $jwt): array
    {
        $user = $this->auth->verifyToken($jwt);
        $accounts = $this->bank->getAccounts($user['id']);

        return [
            'user' => $user,
            'accounts' => $accounts
        ];
    }
}
