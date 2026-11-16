<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchReports;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsProviderCapabilities;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Infrastructure\Services\Service;

/**
 * A source of search performance data. Distinct neutral questions go in, one answer or error per question comes out;
 * the source decides batching, paging of calls, caching and how provider errors map to causes.
 */
abstract class SearchConsoleSourceService extends Service
{
    /** @return InsightsProviderCapabilities what the source can answer */
    abstract public function getCapabilities(): InsightsProviderCapabilities;

    /**
     * @param InsightsSite $insightsSite
     * @param string $searchType
     * @param string $dataState final or all
     * @return SearchDataAvailability up to which day data of the state is there
     */
    abstract public function getAvailability(InsightsSite $insightsSite, string $searchType, string $dataState): SearchDataAvailability;

    /**
     * @param InsightsSite $insightsSite
     * @param SearchQuestions $searchQuestions distinct questions
     * @param bool $useCache false: every question is asked of the provider (an access check)
     * @return SearchReports one answer or error per question
     */
    abstract public function loadReports(InsightsSite $insightsSite, SearchQuestions $searchQuestions, bool $useCache = true): SearchReports;

    /** @return string the earliest day the provider keeps (provider days, `Y-m-d`) */
    abstract public function getEarliestDay(): string;

    /** @return string today in the provider's day zone (`Y-m-d`) */
    abstract public function getToday(): string;
}
