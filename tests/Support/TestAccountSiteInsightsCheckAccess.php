<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsCheckAccess;

/** The command running as the account a test sets (no database lookup of the CLI account) */
class TestAccountSiteInsightsCheckAccess extends SiteInsightsCheckAccess
{
    public ?Account $cliAccount = null;

    public function __construct(?Account $cliAccount)
    {
        $this->cliAccount = $cliAccount;
        parent::__construct('app:site-insights:check-access');
    }

    protected function findAccountForCliOperations(): ?Account
    {
        return $this->cliAccount;
    }
}
