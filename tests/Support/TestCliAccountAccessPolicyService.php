<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;

/** The default access policy with the console account a test sets (no database lookup) */
class TestCliAccountAccessPolicyService extends SiteInsightsAccessPolicyService
{
    public ?Account $cliAccount = null;

    public function findAccountForCliOperations(): ?Account
    {
        return $this->cliAccount;
    }
}
