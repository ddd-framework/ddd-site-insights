<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The filters of the one `and` group of a Search Analytics question.
 *
 * @property GoogleSearchAnalyticsFilter[] $elements
 * @method GoogleSearchAnalyticsFilter[] getElements()
 * @method GoogleSearchAnalyticsFilter|null first()
 */
class GoogleSearchAnalyticsFilters extends ObjectSet
{
    /** A copy of the set in filter order ({@see GoogleSearchAnalyticsFilter::buildSortKey()}) */
    public function buildSorted(): static
    {
        $filters = $this->getElements();
        usort($filters, static fn (GoogleSearchAnalyticsFilter $first, GoogleSearchAnalyticsFilter $second): int => $first->buildSortKey() <=> $second->buildSortKey());
        $sortedFilters = new static();
        foreach ($filters as $filter) {
            $sortedFilters->add($filter);
        }
        return $sortedFilters;
    }

}
