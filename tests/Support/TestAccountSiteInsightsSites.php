<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsSites;

/** The command running as the account a test sets (no database lookup of the CLI account) */
class TestAccountSiteInsightsSites extends SiteInsightsSites
{
    public ?Account $cliAccount = null;

    public function __construct(?Account $cliAccount)
    {
        $this->cliAccount = $cliAccount;
        parent::__construct('app:site-insights:sites');
    }

    protected function findAccountForCliOperations(): ?Account
    {
        return $this->cliAccount;
    }
}
