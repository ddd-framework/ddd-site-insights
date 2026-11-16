<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

/**
 * The overview of a site's search performance in one window. Endpoint and command property
 * `searchPerformanceOverview`. Every part carries its own coverage or `error`; the top lists carry no counterparts
 * (the list endpoints add them).
 */
class SearchPerformanceOverview extends ValueObject
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window; set when a comparison was asked (it may be refused) */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var Date The last day with final data (provider days) */
    public Date $dataThroughDate;

    /** @var string probe or fallback */
    public string $dataThroughDateSource;

    /** @var SearchTotals Totals of the window, by property, with comparison */
    public SearchTotals $totals;

    /** @var SearchPerformanceRows One row per day of the window (days without data filled and marked), compared day by day */
    public SearchPerformanceRows $series;

    /** @var SearchPerformanceRows Top queries by clicks (anonymised queries omitted) */
    public SearchPerformanceRows $topQueries;

    /** @var SearchPerformanceRows Top pages by clicks (page aggregation) */
    public SearchPerformanceRows $topPages;

    /** @var SearchPerformanceRows Countries by clicks */
    public SearchPerformanceRows $countries;

    /** @var SearchPerformanceRows Devices by clicks */
    public SearchPerformanceRows $devices;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
