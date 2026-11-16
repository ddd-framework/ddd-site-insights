<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;

/**
 * The candidates of the opportunity rules for one site and window, grouped by kind in `kinds`. Endpoint and command
 * property `searchOpportunities`. Candidates with evidence, never diagnoses.
 *
 * @property SearchOpportunity[] $elements
 * @method SearchOpportunity[] getElements()
 * @method SearchOpportunity|null first()
 */
class SearchOpportunities extends ObjectSet
{
    /** @var string Key of the site */
    public string $siteKey;

    /** @var SearchWindow The window */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null The comparison window of the rising and declining kinds (previousPeriod unless asked otherwise) */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var SearchOpportunityKindResults One result per kind evaluated */
    public SearchOpportunityKindResults $kinds;

    /** @var int Provider calls of all kinds together (cache hits not counted) */
    public int $providerCalls = 0;

    /** @var int The calls the operation may plan (budgets.callsPerOperation.opportunities) */
    public int $callsAllowed = 0;
}
