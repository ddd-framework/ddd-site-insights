<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Services\SiteInsightsProviderBudgetsService;

/** The budgets with counters in the installed in-memory cache at a fixed moment */
class TestBudgetsService extends SiteInsightsProviderBudgetsService
{
    public int $nowTimestamp = FixedClockGoogleSearchConsoleSourceService::DEFAULT_NOW_TIMESTAMP;

    protected function isApcuUsable(): bool
    {
        return true;
    }

    protected function getNowTimestamp(): int
    {
        return $this->nowTimestamp;
    }
}
