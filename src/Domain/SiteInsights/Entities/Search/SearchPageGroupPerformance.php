<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsPageGroup;

/** One configured page group's aggregate (its own question, page aggregation), with comparison */
class SearchPageGroupPerformance extends ValueObject
{
    /** @var InsightsPageGroup The group */
    public InsightsPageGroup $pageGroup;

    /** @var string The RE2 expression the group was asked with (page includingRegex) */
    public string $pageExpression;

    /** @var SearchTotals The group's metrics with comparison */
    public SearchTotals $totals;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->pageGroup->key ?? (string)spl_object_id($this));
    }
}
