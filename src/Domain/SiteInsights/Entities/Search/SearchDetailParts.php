<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/** The parts of a page or query detail read in one batch: totals and series with comparison, and one list */
class SearchDetailParts extends ValueObject
{
    /** @var SearchTotals Metrics of the subject with comparison */
    public SearchTotals $totals;

    /** @var SearchPerformanceRows Metrics per day with comparison */
    public SearchPerformanceRows $series;

    /** @var SearchPerformanceRows The subject's list (queries of a page, pages of a query) */
    public SearchPerformanceRows $rows;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
