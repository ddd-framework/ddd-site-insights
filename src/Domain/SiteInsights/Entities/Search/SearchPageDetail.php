<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;

/** One page in depth. Endpoint and command property `searchPageDetail`. Metrics are page-aggregated (byPage). */
class SearchPageDetail extends ValueObject
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var string The page URL as asked (trimmed) */
    public string $page;

    /** @var InsightsPageGroup|null The first configured page group whose pattern matches the page's path */
    public ?InsightsPageGroup $pageGroup;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window; set when a comparison was asked */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var SearchTotals The page's metrics with comparison */
    public SearchTotals $totals;

    /** @var SearchPerformanceRows The page's metrics per day, compared day by day */
    public SearchPerformanceRows $series;

    /** @var SearchPerformanceRows The queries the page was shown for (anonymised queries omitted) */
    public SearchPerformanceRows $queries;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
