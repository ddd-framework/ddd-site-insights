<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;

/** An application's policy that lets admins view but not inspect */
class ViewOnlyAccessPolicyService extends SiteInsightsAccessPolicyService
{
    public function canInspect(?Account $account): bool
    {
        return false;
    }
}
