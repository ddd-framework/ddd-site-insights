<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsOpportunitiesConfig;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;

/**
 * The working state of one opportunities request inside SearchOpportunitiesService: the site, the windows, the
 * thresholds, the calls used, and every source read so far (shared by the kinds that need it). Never returned.
 */
class SearchOpportunitiesEvaluation extends ValueObject
{
    /** @var InsightsSite The site */
    public InsightsSite $insightsSite;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window of the rising and declining kinds */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var SiteInsightsOpportunitiesConfig The site's thresholds */
    public SiteInsightsOpportunitiesConfig $opportunitiesConfig;

    /** @var int Provider calls planned so far (the probe counts as one) */
    public int $plannedCalls = 1;

    /** @var int Provider calls made */
    public int $providerCalls = 0;

    /** @var int budgets.callsPerOperation.opportunities */
    public int $callsAllowed = 0;

    /** @var SearchPerformanceRows|null Queries of the window in provider order, up to budgets.sortFetchCeiling */
    public ?SearchPerformanceRows $queryRowsOfWindow;

    /** @var SearchPerformanceRows|null Query × device rows of the window, up to budgets.sortFetchCeiling */
    public ?SearchPerformanceRows $queryDeviceRowsOfWindow;

    /** @var SearchPerformanceRows|null Query × page rows of the top queries */
    public ?SearchPerformanceRows $queryPageRowsOfTopQueries;

    /** @var SearchPerformanceRows|null Candidate queries of both periods with their counterparts */
    public ?SearchPerformanceRows $comparedQueryRows;

    /** @var SearchPerformanceRows|null Candidate pages of both periods with their counterparts */
    public ?SearchPerformanceRows $comparedPageRows;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
