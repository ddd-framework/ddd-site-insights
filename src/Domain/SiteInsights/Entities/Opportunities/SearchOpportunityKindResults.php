<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * One result per kind evaluated, in the order of SearchOpportunity::KINDS.
 *
 * @property SearchOpportunityKindResult[] $elements
 * @method SearchOpportunityKindResult[] getElements()
 * @method SearchOpportunityKindResult|null first()
 */
class SearchOpportunityKindResults extends ObjectSet
{
    /**
     * @param string $kind
     * @return SearchOpportunityKindResult|null
     */
    public function findKindResult(string $kind): ?SearchOpportunityKindResult
    {
        return $this->getByUniqueKey(SearchOpportunityKindResult::uniqueKeyStatic($kind));
    }
}
