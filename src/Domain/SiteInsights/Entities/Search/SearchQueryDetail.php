<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/** One query in depth. Endpoint and command property `searchQueryDetail`. Metrics are property-aggregated. */
class SearchQueryDetail extends ValueObject
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var string The query as asked (trimmed, exact match) */
    public string $query;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window; set when a comparison was asked */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var SearchTotals The query's metrics with comparison */
    public SearchTotals $totals;

    /** @var SearchPerformanceRows The query's metrics per day, compared day by day */
    public SearchPerformanceRows $series;

    /** @var SearchPerformanceRows The pages shown for the query (page aggregation) */
    public SearchPerformanceRows $pages;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
