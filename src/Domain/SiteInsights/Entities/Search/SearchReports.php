<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * One answer per distinct question.
 *
 * @property SearchReport[] $elements
 * @method SearchReport[] getElements()
 * @method SearchReport|null first()
 */
class SearchReports extends ObjectSet
{
    /**
     * @param SearchQuestion $searchQuestion
     * @return SearchReport|null the answer to that question
     */
    public function findReportForQuestion(SearchQuestion $searchQuestion): ?SearchReport
    {
        $questionKey = $searchQuestion->uniqueKey();
        foreach ($this->getElements() as $searchReport) {
            if ($searchReport->question->uniqueKey() === $questionKey) {
                return $searchReport;
            }
        }
        return null;
    }
}
