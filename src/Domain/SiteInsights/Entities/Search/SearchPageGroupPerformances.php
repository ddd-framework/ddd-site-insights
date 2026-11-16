<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * One aggregate per configured page group. Endpoint and command property `searchPageGroupPerformances`. Groups may
 * overlap and are never summed; page aggregation counts higher than the property totals.
 *
 * @property SearchPageGroupPerformance[] $elements
 * @method SearchPageGroupPerformance[] getElements()
 * @method SearchPageGroupPerformance|null first()
 */
class SearchPageGroupPerformances extends ObjectSet
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window; set when a comparison was asked */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var bool Always true: a page can belong to several groups, so group numbers must not be added up */
    public bool $groupsMayOverlap = true;

    /** @var string byPage: each group counts per page, which is higher than the property totals */
    public string $aggregation = SearchMetrics::AGGREGATION_BY_PAGE;

    /** @var int Configured groups beyond budgets.maxPageGroups that were not asked */
    public int $pageGroupsLeftOut = 0;
}
