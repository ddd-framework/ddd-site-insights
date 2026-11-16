<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatuses;
use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspections;
use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchReports;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsProviderCapabilities;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Infrastructure\Services\Service;

/**
 * A source of search performance data and index states. Distinct neutral questions go in, one answer or error per
 * question comes out; the source decides batching, paging of calls, caching and how provider errors map to causes.
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

    /**
     * @param InsightsSite $insightsSite
     * @return SitemapStatuses the sitemap files of the property with their state and coverage, or the list's error
     */
    abstract public function loadSitemapStatuses(InsightsSite $insightsSite): SitemapStatuses;

    /**
     * Inspects URLs in one call round; every URL not answered from the cache counts against the provider's daily
     * inspection quota.
     *
     * @param InsightsSite $insightsSite
     * @param string[] $inspectionUrls distinct URLs of the property, validated by the caller
     * @param string|null $languageCode
     * @param bool $useCache
     * @return UrlIndexInspections one state or error per URL, in the order given, with coverage
     */
    abstract public function inspectUrls(InsightsSite $insightsSite, array $inspectionUrls, ?string $languageCode = null, bool $useCache = true): UrlIndexInspections;
}
