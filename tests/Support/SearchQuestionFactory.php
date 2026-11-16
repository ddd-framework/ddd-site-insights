<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilter;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilters;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Utils\SearchDays;

/** Builds neutral questions for tests */
class SearchQuestionFactory
{
    /**
     * @param string[] $dimensions
     * @param string[][] $filterTriples [dimension, operator, expression]
     */
    public static function create(
        array $dimensions = [],
        string $startDay = '2026-10-17',
        string $endDay = '2026-11-13',
        int $rowLimit = 25,
        array $filterTriples = [],
        string $aggregationType = 'auto',
        string $dataState = 'final',
        int $startRow = 0
    ): SearchQuestion {
        $searchQuestion = new SearchQuestion();
        $searchQuestion->startDate = SearchDays::toDate($startDay);
        $searchQuestion->endDate = SearchDays::toDate($endDay);
        $searchQuestion->dimensions = $dimensions;
        $searchQuestion->rowLimit = $rowLimit;
        $searchQuestion->aggregationType = $aggregationType;
        $searchQuestion->dataState = $dataState;
        $searchQuestion->startRow = $startRow;
        $searchQuestionFilters = new SearchQuestionFilters();
        foreach ($filterTriples as [$dimension, $operator, $expression]) {
            $searchQuestionFilter = SearchQuestionFilter::create($dimension, $operator, $expression);
            $searchQuestionFilters->add($searchQuestionFilter);
        }
        $searchQuestion->filters = $searchQuestionFilters;
        return $searchQuestion;
    }

    public static function createSet(SearchQuestion ...$searchQuestions): SearchQuestions
    {
        $searchQuestionSet = new SearchQuestions();
        foreach ($searchQuestions as $searchQuestion) {
            $searchQuestionSet->add($searchQuestion);
        }
        return $searchQuestionSet;
    }
}
