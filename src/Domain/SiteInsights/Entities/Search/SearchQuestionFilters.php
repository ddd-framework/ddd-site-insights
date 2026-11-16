<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The filters of a neutral question (all must match).
 *
 * @property SearchQuestionFilter[] $elements
 * @method SearchQuestionFilter[] getElements()
 * @method SearchQuestionFilter|null first()
 */
class SearchQuestionFilters extends ObjectSet
{
    /** A copy of the set in filter order ({@see SearchQuestionFilter::buildSortKey()}) */
    public function buildSorted(): static
    {
        $filters = $this->getElements();
        usort($filters, static fn (SearchQuestionFilter $first, SearchQuestionFilter $second): int => $first->buildSortKey() <=> $second->buildSortKey());
        $sortedFilters = new static();
        foreach ($filters as $filter) {
            $sortedFilters->add($filter);
        }
        return $sortedFilters;
    }

}
