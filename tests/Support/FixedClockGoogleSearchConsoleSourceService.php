<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReport;
use DDD\Domain\SiteInsights\Services\GoogleSearchConsoleSourceService;

/** The Google source at a fixed moment (default: 2026-11-16 12:00 Pacific Time) */
class FixedClockGoogleSearchConsoleSourceService extends GoogleSearchConsoleSourceService
{
    /** 2026-11-16 20:00:00 UTC = 12:00 Pacific Time, a Monday */
    public const int DEFAULT_NOW_TIMESTAMP = 1794859200;

    public int $nowTimestamp = self::DEFAULT_NOW_TIMESTAMP;

    /** @var int|null The moment cache ages are measured at; null: the real clock (`fetchedAt` is written with it) */
    public ?int $freshnessCheckTimestamp = null;

    /** The Argus report the source asks for a question (cache level and lifetime set) */
    public function buildArgusReportOfQuestion(InsightsSite $insightsSite, SearchQuestion $searchQuestion): ArgusGoogleSearchAnalyticsReport
    {
        return $this->buildArgusReport($insightsSite, $searchQuestion);
    }

    protected function getNowTimestamp(): int
    {
        return $this->nowTimestamp;
    }

    protected function getFreshnessCheckTimestamp(): int
    {
        return $this->freshnessCheckTimestamp ?? time();
    }
}
