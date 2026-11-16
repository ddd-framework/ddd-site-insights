<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Distinct questions: a question with the identity of one already in the set is not added again.
 *
 * @property SearchQuestion[] $elements
 * @method SearchQuestion[] getElements()
 * @method SearchQuestion|null first()
 * @method SearchQuestion|null getByUniqueKey(string $uniqueKey)
 */
class SearchQuestions extends ObjectSet
{
}
