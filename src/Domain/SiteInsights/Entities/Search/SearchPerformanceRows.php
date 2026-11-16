<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * A list of rows with its window and coverage. Endpoint property `searchPerformanceRows`. When the list failed,
 * `error` is set and there are no rows.
 *
 * @property SearchPerformanceRow[] $elements
 * @method SearchPerformanceRow[] getElements()
 * @method SearchPerformanceRow|null first()
 */
class SearchPerformanceRows extends ObjectSet
{
    /** @var string Provider key, e.g. google-search-console */
    public string $provider;

    /** @var string[] Dimensions the rows are grouped by */
    public array $dimensions = [];

    /** @var SearchWindow The window of the rows */
    public SearchWindow $window;

    /** @var SearchComparisonWindow|null Set when a comparison was asked */
    public ?SearchComparisonWindow $comparisonWindow;

    /** @var SearchRowsCoverage What the rows cover */
    public SearchRowsCoverage $coverage;

    /** @var SearchPartError|null Set instead of rows when this list failed */
    public ?SearchPartError $error;
}
