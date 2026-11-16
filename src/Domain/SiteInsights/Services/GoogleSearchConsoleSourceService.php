<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCacheInFlightComputation;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Google\GoogleProviderAnswer;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsFilter;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsFilters;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSearchAnalyticsReport;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSite;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSitemaps;
use DDD\Domain\SiteInsights\Entities\Google\GoogleSites;
use DDD\Domain\SiteInsights\Entities\Google\GoogleUrlInspection;
use DDD\Domain\SiteInsights\Entities\Indexing\IndexingCoverage;
use DDD\Domain\SiteInsights\Entities\Indexing\SitemapContentCount;
use DDD\Domain\SiteInsights\Entities\Indexing\SitemapContentCounts;
use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatus;
use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatuses;
use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspection;
use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspections;
use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchReport;
use DDD\Domain\SiteInsights\Entities\Search\SearchReports;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsProviderCapabilities;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReport;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSearchAnalyticsReports;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSite;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSitemaps;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleSites;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleUrlInspection;
use DDD\Domain\SiteInsights\Repo\Argus\Google\ArgusGoogleUrlInspections;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use RuntimeException;

/**
 * Google Search Console as a search data source: batches of distinct questions in settle mode, the module's own cache
 * reads and freshness check, joined cold computations, the mapping of Google's rows to neutral rows and of Google's
 * errors to causes.
 *
 * Failure handling:
 * - a rejected call (settle mode) fails its own part only (`transport`);
 * - an oversized, stalled or incomplete private answer throws for the whole batch in Argus: the batch is then asked
 *   once more part by part, and a part that fails again alone gets `answerUnreadable`;
 * - a 401 drops the cached token and asks the affected parts once more; a second 401 is `tokenRefused`;
 * - failures are never cached.
 */
class GoogleSearchConsoleSourceService extends SearchConsoleSourceService
{
    public const string PROVIDER = GoogleProviderAnswer::PROVIDER;
    public const string DAY_TIME_ZONE = 'America/Los_Angeles';

    /** @var string[] */
    public const array DIMENSIONS = GoogleSearchAnalyticsReport::CANONICAL_DIMENSION_ORDER;

    /** @var string[] */
    public const array FILTER_DIMENSIONS = ['query', 'page', 'country', 'device', 'searchAppearance'];

    /** @var string[] */
    public const array FILTER_OPERATORS = ['equals', 'contains', 'notEquals', 'notContains', 'includingRegex', 'excludingRegex'];

    /** @var string[] */
    public const array METRICS = ['clicks', 'impressions', 'ctr', 'position'];

    /** @var string[] */
    public const array SEARCH_TYPES = ['web', 'image', 'video', 'news', 'discover', 'googleNews'];

    /** @var string[] */
    public const array DATA_STATES = [SearchWindowRequest::DATA_STATE_FINAL, SearchWindowRequest::DATA_STATE_ALL];

    /** @var string[] */
    public const array AGGREGATIONS = [SearchQuestion::AGGREGATION_AUTO, SearchQuestion::AGGREGATION_BY_PAGE, SearchQuestion::AGGREGATION_BY_PROPERTY];

    /** @var int Google's most rows per call */
    public const int PROVIDER_MAX_ROW_LIMIT = 25000;

    /** @var int Days the availability probe looks back */
    public const int PROBE_DAYS = 10;

    /** @var int Days behind today a final day is assumed when the probe has no row */
    public const int FALLBACK_DATA_LAG_DAYS = 3;

    public function getCapabilities(): InsightsProviderCapabilities
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $insightsProviderCapabilities = new InsightsProviderCapabilities();
        $insightsProviderCapabilities->provider = self::PROVIDER;
        $insightsProviderCapabilities->dimensions = self::DIMENSIONS;
        $insightsProviderCapabilities->filterOperators = self::FILTER_OPERATORS;
        $insightsProviderCapabilities->metrics = self::METRICS;
        $insightsProviderCapabilities->searchTypes = self::SEARCH_TYPES;
        $insightsProviderCapabilities->dataStates = self::DATA_STATES;
        $insightsProviderCapabilities->aggregations = self::AGGREGATIONS;
        $insightsProviderCapabilities->dayTimeZone = self::DAY_TIME_ZONE;
        $insightsProviderCapabilities->retentionMonths = $budgetsConfig->retentionMonths;
        $insightsProviderCapabilities->maxRowsPerCall = min(self::PROVIDER_MAX_ROW_LIMIT, $budgetsConfig->maxRowsPerCall);
        $insightsProviderCapabilities->hasHourlyData = false;
        return $insightsProviderCapabilities;
    }

    public function getToday(): string
    {
        return SearchDays::todayInZone(self::DAY_TIME_ZONE, $this->getNowTimestamp());
    }

    public function getEarliestDay(): string
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        return SearchDays::addDays(
            SearchDays::subtractMonthsClamped($this->getToday(), $budgetsConfig->retentionMonths),
            $budgetsConfig->retentionSafetyDays
        );
    }

    /**
     * The last final day is the latest day of a question by date over the last ten days (Google names no such day).
     * For `all` a second probe with that state gives the first incomplete day. Both are asked in one batch and cached
     * `cache.probeTtlSeconds`.
     */
    public function getAvailability(InsightsSite $insightsSite, string $searchType, string $dataState): SearchDataAvailability
    {
        $today = $this->getToday();
        $searchQuestions = new SearchQuestions();
        $finalProbeQuestion = $this->buildProbeQuestion($searchType, SearchWindowRequest::DATA_STATE_FINAL, $today);
        $searchQuestions->add($finalProbeQuestion);
        $allProbeQuestion = null;
        if ($dataState === SearchWindowRequest::DATA_STATE_ALL) {
            $allProbeQuestion = $this->buildProbeQuestion($searchType, SearchWindowRequest::DATA_STATE_ALL, $today);
            $searchQuestions->add($allProbeQuestion);
        }
        $searchReports = $this->loadReports($insightsSite, $searchQuestions);

        $searchDataAvailability = new SearchDataAvailability();
        $searchDataAvailability->searchType = $searchType;
        $searchDataAvailability->dataState = $dataState;
        $finalProbeReport = $searchReports->findReportForQuestion($finalProbeQuestion);
        $latestFinalDay = $finalProbeReport?->latestReportedDay ?? null;
        if ($finalProbeReport !== null && $latestFinalDay !== null && !isset($finalProbeReport->performanceRows->error)) {
            $searchDataAvailability->dataThroughDate = SearchDays::toDate($latestFinalDay);
            $searchDataAvailability->dataThroughDateSource = SearchWindow::DATA_THROUGH_DATE_SOURCE_PROBE;
        } else {
            $searchDataAvailability->dataThroughDate = SearchDays::toDate(SearchDays::addDays($today, -self::FALLBACK_DATA_LAG_DAYS));
            $searchDataAvailability->dataThroughDateSource = SearchWindow::DATA_THROUGH_DATE_SOURCE_FALLBACK;
        }
        if (isset($finalProbeReport->performanceRows->error)) {
            $searchDataAvailability->error = $finalProbeReport->performanceRows->error;
        }
        if (isset($finalProbeReport->performanceRows->coverage->fetchedAt)) {
            $searchDataAvailability->fetchedAt = $finalProbeReport->performanceRows->coverage->fetchedAt;
        }
        if ($allProbeQuestion !== null) {
            $firstIncompleteDay = $searchReports->findReportForQuestion($allProbeQuestion)?->firstIncompleteDay ?? null;
            if ($firstIncompleteDay !== null) {
                $searchDataAvailability->firstIncompleteDate = SearchDays::toDate($firstIncompleteDay);
            }
        }
        return $searchDataAvailability;
    }

    /**
     * @throws BadRequestException when a question is not one Google can answer
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained (nothing is sent)
     */
    public function loadReports(InsightsSite $insightsSite, SearchQuestions $searchQuestions, bool $useCache = true): SearchReports
    {
        // method-local maps by question identity: the questions, their Argus reports, whether answered from cache
        $searchQuestionsByKey = [];
        foreach ($searchQuestions->getElements() as $searchQuestion) {
            $this->validateQuestion($searchQuestion);
            $searchQuestionsByKey[$searchQuestion->uniqueKey()] ??= $searchQuestion;
        }
        $answeredReportsByKey = [];
        $answeredFromCacheByKey = [];
        $argusReportsToAskByKey = [];
        $inFlightComputations = [];
        $argusReportsByKey = [];
        $cacheLevelsByCacheKey = [];
        $cacheTtlsByCacheKey = [];
        foreach ($searchQuestionsByKey as $questionKey => $searchQuestion) {
            $argusReport = $this->buildArgusReport($insightsSite, $searchQuestion);
            $argusReportsByKey[$questionKey] = $argusReport;
            $cacheLevelsByCacheKey[$argusReport->cacheKey()] = $argusReport->getArgusSettings()->argusLoad->cacheLevel;
            $cacheTtlsByCacheKey[$argusReport->cacheKey()] = $argusReport->getArgusSettings()->argusLoad->cacheTtl;
        }
        $cachedEntriesByCacheKey = $useCache ? $this->readCachedEntries($cacheLevelsByCacheKey, $cacheTtlsByCacheKey) : [];
        foreach ($searchQuestionsByKey as $questionKey => $searchQuestion) {
            $argusReport = $argusReportsByKey[$questionKey];
            if (!$useCache) {
                $argusReportsToAskByKey[$questionKey] = $argusReport;
                continue;
            }
            $cacheKey = $argusReport->cacheKey();
            $lifetimeSeconds = $cacheTtlsByCacheKey[$cacheKey];
            $cachedReport = $this->hydrateFreshCachedReport($insightsSite, $searchQuestion, $cachedEntriesByCacheKey[$cacheKey] ?? null, $lifetimeSeconds);
            if ($cachedReport !== null) {
                $answeredReportsByKey[$questionKey] = $cachedReport;
                $answeredFromCacheByKey[$questionKey] = true;
                continue;
            }
            $argusCacheInFlightComputation = new ArgusCacheInFlightComputation($cacheKey);
            if (isset($cachedEntriesByCacheKey[$cacheKey])) {
                // a stale entry exists: joining would only find it again
                $argusCacheInFlightComputation->claimComputation();
            } elseif (!$argusCacheInFlightComputation->joinPendingComputationOrClaim($this->getSiteInsightsConfigService()->getCacheConfig()->joinWaitMilliseconds)) {
                // another process wrote the entry meanwhile
                $joinedEntries = $this->readCachedEntries([$cacheKey => $cacheLevelsByCacheKey[$cacheKey]], [$cacheKey => $lifetimeSeconds]);
                $joinedReport = $this->hydrateFreshCachedReport($insightsSite, $searchQuestion, $joinedEntries[$cacheKey] ?? null, $lifetimeSeconds);
                if ($joinedReport !== null) {
                    $answeredReportsByKey[$questionKey] = $joinedReport;
                    $answeredFromCacheByKey[$questionKey] = true;
                    continue;
                }
                $argusCacheInFlightComputation->claimComputation();
            }
            $inFlightComputations[] = $argusCacheInFlightComputation;
            $argusReportsToAskByKey[$questionKey] = $argusReport;
        }

        try {
            if ($argusReportsToAskByKey) {
                $argusGoogleSearchAnalyticsReports = new ArgusGoogleSearchAnalyticsReports();
                foreach ($argusReportsToAskByKey as $argusReport) {
                    $argusGoogleSearchAnalyticsReports->add($argusReport);
                }
                $this->loadBatch($insightsSite, $argusGoogleSearchAnalyticsReports);
                $this->retryUnauthorizedParts($insightsSite, $argusGoogleSearchAnalyticsReports);
                $answeredReportsByKey += $argusReportsToAskByKey;
            }
        } finally {
            foreach ($inFlightComputations as $argusCacheInFlightComputation) {
                $argusCacheInFlightComputation->releaseComputation();
            }
        }

        $searchReports = new SearchReports();
        foreach ($searchQuestionsByKey as $questionKey => $searchQuestion) {
            $searchReport = $this->buildSearchReport(
                $answeredReportsByKey[$questionKey],
                $searchQuestion,
                $answeredFromCacheByKey[$questionKey] ?? false
            );
            $searchReports->add($searchReport);
        }
        return $searchReports;
    }

    /**
     * Maps Google's answer to neutral rows: `keys[i]` goes to the property of dimension i, dates through
     * SearchDays::toDate(), devices in lower case, clicks and impressions rounded to integers, the click rate
     * recomputed from them, the position only when Google sent one.
     *
     * @param GoogleSearchAnalyticsReport $googleSearchAnalyticsReport
     * @param SearchQuestion $searchQuestion
     * @param bool $fromCache whether the answer came from the cache
     * @return SearchPerformanceRows rows and coverage, or the error of the part
     */
    public function mapRows(GoogleSearchAnalyticsReport $googleSearchAnalyticsReport, SearchQuestion $searchQuestion, bool $fromCache = false): SearchPerformanceRows
    {
        $canonicalDimensions = $googleSearchAnalyticsReport->getCanonicalDimensions();
        $searchPerformanceRows = new SearchPerformanceRows();
        $searchPerformanceRows->provider = self::PROVIDER;
        $searchPerformanceRows->dimensions = $canonicalDimensions;
        $searchRowsCoverage = new SearchRowsCoverage();
        $searchRowsCoverage->rowLimitPerCall = $googleSearchAnalyticsReport->rowLimit;
        $searchRowsCoverage->fromCache = $fromCache;
        $searchRowsCoverage->providerCalls = $googleSearchAnalyticsReport instanceof ArgusGoogleSearchAnalyticsReport
            ? $googleSearchAnalyticsReport->getProviderCallAttempts()
            : 0;
        $searchRowsCoverage->requestedAggregation = $googleSearchAnalyticsReport->aggregationType;
        $filterDimensions = array_map(
            static fn (GoogleSearchAnalyticsFilter $googleSearchAnalyticsFilter): string => $googleSearchAnalyticsFilter->dimension,
            array_values($googleSearchAnalyticsReport->getSortedFilters()->getElements())
        );
        $searchRowsCoverage->anonymizedQueriesOmitted = in_array('query', $canonicalDimensions, true) || in_array('query', $filterDimensions, true);
        $searchRowsCoverage->joinedDimensionsMayLoseRows = in_array('query', $canonicalDimensions, true) && in_array('page', $canonicalDimensions, true);
        $answeredAggregation = self::mapResponseAggregationType($googleSearchAnalyticsReport->responseAggregationType ?? null);
        $responseAggregation = $answeredAggregation
            ?? $this->determineAggregationOfRequest($googleSearchAnalyticsReport->aggregationType, $canonicalDimensions, $filterDimensions);
        $searchRowsCoverage->responseAggregation = $responseAggregation;
        $searchRowsCoverage->responseAggregationKnown = $answeredAggregation !== null;
        $searchPerformanceRows->coverage = $searchRowsCoverage;

        if (isset($googleSearchAnalyticsReport->failure) || !isset($googleSearchAnalyticsReport->rows)) {
            $googleApiFailure = $googleSearchAnalyticsReport->failure ?? null;
            $searchPerformanceRows->error = $googleApiFailure !== null
                ? SearchPartError::create($this->classifyFailure($googleApiFailure), $googleApiFailure->message, $googleApiFailure->httpStatus ?? null)
                : SearchPartError::create(SiteInsightsErrorCause::PROVIDER_FAILED, 'no answer');
            return $searchPerformanceRows;
        }
        if (isset($googleSearchAnalyticsReport->fetchedAt)) {
            $searchRowsCoverage->fetchedAt = $googleSearchAnalyticsReport->fetchedAt;
        }
        foreach ($googleSearchAnalyticsReport->rows->getElements() as $googleSearchAnalyticsRow) {
            $searchPerformanceRow = new SearchPerformanceRow();
            foreach ($canonicalDimensions as $dimensionIndex => $dimension) {
                $keyValue = (string)($googleSearchAnalyticsRow->keys[$dimensionIndex] ?? '');
                match ($dimension) {
                    'date' => SearchDays::isValidDay($keyValue) ? $searchPerformanceRow->date = SearchDays::toDate($keyValue) : null,
                    'query' => $searchPerformanceRow->query = $keyValue,
                    'page' => $searchPerformanceRow->page = $keyValue,
                    'country' => $searchPerformanceRow->country = strtolower($keyValue),
                    'device' => $searchPerformanceRow->device = strtolower($keyValue),
                    'searchAppearance' => $searchPerformanceRow->searchAppearance = $keyValue,
                    default => null,
                };
            }
            $searchMetrics = SearchMetrics::create(
                (int)round($googleSearchAnalyticsRow->clicks),
                (int)round($googleSearchAnalyticsRow->impressions),
                $googleSearchAnalyticsRow->position ?? null,
                $responseAggregation
            );
            $searchPerformanceRow->metrics = $searchMetrics;
            $searchPerformanceRows->add($searchPerformanceRow);
        }
        $rowCount = count($googleSearchAnalyticsReport->rows->getElements());
        $searchRowsCoverage->fetchedRows = $rowCount;
        if ($this->asksForEveryRowThereIs($googleSearchAnalyticsReport)) {
            // totals, or a series asked for every day of its window: no row can be beyond the answer
            $searchRowsCoverage->providerRowsExhausted = true;
            $searchRowsCoverage->limitReached = false;
        } else {
            $searchRowsCoverage->providerRowsExhausted = $rowCount < $googleSearchAnalyticsReport->rowLimit;
            $searchRowsCoverage->limitReached = $rowCount >= $googleSearchAnalyticsReport->rowLimit;
        }
        return $searchPerformanceRows;
    }

    /**
     * @param GoogleSearchAnalyticsReport $googleSearchAnalyticsReport
     * @return bool whether the question's row limit covers every row that can exist: no dimension (one aggregate), or
     *         only `date` with a row limit of at least the window's days
     */
    protected function asksForEveryRowThereIs(GoogleSearchAnalyticsReport $googleSearchAnalyticsReport): bool
    {
        $canonicalDimensions = $googleSearchAnalyticsReport->getCanonicalDimensions();
        if ($canonicalDimensions === []) {
            return true;
        }
        if ($canonicalDimensions !== ['date']) {
            return false;
        }
        $windowDays = SearchDays::daysInclusive(
            SearchDays::fromDate($googleSearchAnalyticsReport->startDate),
            SearchDays::fromDate($googleSearchAnalyticsReport->endDate)
        );
        return $googleSearchAnalyticsReport->startRow === 0 && $googleSearchAnalyticsReport->rowLimit >= $windowDays;
    }

    /**
     * @param GoogleApiFailure $googleApiFailure
     * @return SiteInsightsErrorCause
     */
    public function classifyFailure(GoogleApiFailure $googleApiFailure): SiteInsightsErrorCause
    {
        return $googleApiFailure->getCause();
    }

    /**
     * @param InsightsSite $insightsSite
     * @return GoogleSites the sites list of the site's credentials entry, asked live (never cached)
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function loadSitesList(InsightsSite $insightsSite): GoogleSites
    {
        $argusGoogleSites = new ArgusGoogleSites();
        $argusGoogleSites->credentialsEntryName = $insightsSite->credentialsEntryName;
        $argusGoogleSites->credentialsRevision = $insightsSite->credentialsRevision;
        $this->loadSingleAnswer($insightsSite, $argusGoogleSites, SiteInsightsProviderBudgetsService::QUOTA_OTHER, false, 0);
        return $argusGoogleSites;
    }

    /**
     * @param InsightsSite $insightsSite
     * @return GoogleSite the property with the account's permission level, asked live (never cached)
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function loadSite(InsightsSite $insightsSite): GoogleSite
    {
        $argusGoogleSite = new ArgusGoogleSite();
        $argusGoogleSite->credentialsEntryName = $insightsSite->credentialsEntryName;
        $argusGoogleSite->credentialsRevision = $insightsSite->credentialsRevision;
        $argusGoogleSite->siteUrl = $insightsSite->property;
        $this->loadSingleAnswer($insightsSite, $argusGoogleSite, SiteInsightsProviderBudgetsService::QUOTA_OTHER, false, 0);
        return $argusGoogleSite;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param bool $useCache
     * @return GoogleSitemaps the sitemaps list of the property (cached `cache.sitemapsTtlSeconds`)
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function loadSitemaps(InsightsSite $insightsSite, bool $useCache = true): GoogleSitemaps
    {
        $argusGoogleSitemaps = $this->buildArgusSitemaps($insightsSite);
        $this->loadSingleAnswer(
            $insightsSite,
            $argusGoogleSitemaps,
            SiteInsightsProviderBudgetsService::QUOTA_OTHER,
            $useCache,
            $this->getSiteInsightsConfigService()->getCacheConfig()->sitemapsTtlSeconds
        );
        return $argusGoogleSitemaps;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $inspectionUrl a URL of the property
     * @param string|null $languageCode
     * @param bool $useCache
     * @return GoogleUrlInspection the URL's index state (cached `cache.inspectionTtlSeconds`)
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function inspectUrl(InsightsSite $insightsSite, string $inspectionUrl, ?string $languageCode = null, bool $useCache = true): GoogleUrlInspection
    {
        $argusGoogleUrlInspection = $this->buildArgusUrlInspection($insightsSite, $inspectionUrl, $languageCode);
        $this->loadSingleAnswer(
            $insightsSite,
            $argusGoogleUrlInspection,
            SiteInsightsProviderBudgetsService::QUOTA_INSPECTION,
            $useCache,
            $this->getSiteInsightsConfigService()->getCacheConfig()->inspectionTtlSeconds
        );
        return $argusGoogleUrlInspection;
    }

    /**
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained (nothing is sent)
     */
    public function loadSitemapStatuses(InsightsSite $insightsSite): SitemapStatuses
    {
        $argusGoogleSitemaps = $this->buildArgusSitemaps($insightsSite);
        $lifetimeSeconds = $this->getSiteInsightsConfigService()->getCacheConfig()->sitemapsTtlSeconds;
        $providerCalls = $this->loadSingleAnswer($insightsSite, $argusGoogleSitemaps, SiteInsightsProviderBudgetsService::QUOTA_OTHER, true, $lifetimeSeconds);
        $fromCache = $providerCalls === 0 && $argusGoogleSitemaps->getArgusSettings()->loadedFromCache && $argusGoogleSitemaps->hasAnswer();
        return $this->mapSitemaps($argusGoogleSitemaps, $insightsSite, $fromCache, $providerCalls, $lifetimeSeconds);
    }

    /**
     * Distinct URLs in one call round in settle mode: cache hits first (the module's own freshness check), questions
     * another process is already asking are joined, the rest is reserved against the inspection quota in one step and
     * asked together. A refused reservation fails those URLs with `budgetRefused` and asks nothing.
     *
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained (nothing is sent)
     */
    public function inspectUrls(InsightsSite $insightsSite, array $inspectionUrls, ?string $languageCode = null, bool $useCache = true): UrlIndexInspections
    {
        $lifetimeSeconds = $this->getSiteInsightsConfigService()->getCacheConfig()->inspectionTtlSeconds;
        // method-local maps by URL: the Argus inspections, which came from the cache, which are to be asked
        $argusInspectionsByUrl = [];
        $cacheLevelsByCacheKey = [];
        $cacheTtlsByCacheKey = [];
        foreach ($inspectionUrls as $inspectionUrl) {
            $inspectionUrl = (string)$inspectionUrl;
            if (isset($argusInspectionsByUrl[$inspectionUrl])) {
                continue;
            }
            $argusGoogleUrlInspection = $this->buildArgusUrlInspection($insightsSite, $inspectionUrl, $languageCode);
            $argusInspectionsByUrl[$inspectionUrl] = $argusGoogleUrlInspection;
            $cacheLevelsByCacheKey[$argusGoogleUrlInspection->cacheKey()] = $argusGoogleUrlInspection->getArgusSettings()->argusLoad->cacheLevel;
            $cacheTtlsByCacheKey[$argusGoogleUrlInspection->cacheKey()] = $lifetimeSeconds;
        }
        $cachedEntriesByCacheKey = $useCache ? $this->readCachedEntries($cacheLevelsByCacheKey, $cacheTtlsByCacheKey) : [];
        $answeredInspectionsByUrl = [];
        $answeredFromCacheByUrl = [];
        $argusInspectionsToAskByUrl = [];
        $inFlightComputations = [];
        foreach ($argusInspectionsByUrl as $inspectionUrl => $argusGoogleUrlInspection) {
            $inspectionUrl = (string)$inspectionUrl;
            if (!$useCache) {
                $argusInspectionsToAskByUrl[$inspectionUrl] = $argusGoogleUrlInspection;
                continue;
            }
            $cacheKey = $argusGoogleUrlInspection->cacheKey();
            $cachedInspection = $this->hydrateFreshCachedInspection($insightsSite, $inspectionUrl, $languageCode, $cachedEntriesByCacheKey[$cacheKey] ?? null, $lifetimeSeconds);
            if ($cachedInspection !== null) {
                $answeredInspectionsByUrl[$inspectionUrl] = $cachedInspection;
                $answeredFromCacheByUrl[$inspectionUrl] = true;
                continue;
            }
            $argusCacheInFlightComputation = new ArgusCacheInFlightComputation($cacheKey);
            if (isset($cachedEntriesByCacheKey[$cacheKey])) {
                // a stale entry exists: joining would only find it again
                $argusCacheInFlightComputation->claimComputation();
            } elseif (!$argusCacheInFlightComputation->joinPendingComputationOrClaim($this->getSiteInsightsConfigService()->getCacheConfig()->joinWaitMilliseconds)) {
                // another process wrote the entry meanwhile
                $joinedEntries = $this->readCachedEntries([$cacheKey => $cacheLevelsByCacheKey[$cacheKey]], [$cacheKey => $lifetimeSeconds]);
                $joinedInspection = $this->hydrateFreshCachedInspection($insightsSite, $inspectionUrl, $languageCode, $joinedEntries[$cacheKey] ?? null, $lifetimeSeconds);
                if ($joinedInspection !== null) {
                    $answeredInspectionsByUrl[$inspectionUrl] = $joinedInspection;
                    $answeredFromCacheByUrl[$inspectionUrl] = true;
                    continue;
                }
                $argusCacheInFlightComputation->claimComputation();
            }
            $inFlightComputations[] = $argusCacheInFlightComputation;
            $argusInspectionsToAskByUrl[$inspectionUrl] = $argusGoogleUrlInspection;
        }

        $inspectionsCounted = 0;
        try {
            if ($argusInspectionsToAskByUrl) {
                $argusGoogleUrlInspections = new ArgusGoogleUrlInspections();
                foreach ($argusInspectionsToAskByUrl as $argusGoogleUrlInspection) {
                    $argusGoogleUrlInspections->add($argusGoogleUrlInspection);
                }
                $inspectionsCounted += $this->loadInspectionBatch($insightsSite, $argusGoogleUrlInspections);
                $inspectionsCounted += $this->retryUnauthorizedInspections($insightsSite, $argusGoogleUrlInspections);
                $answeredInspectionsByUrl += $argusInspectionsToAskByUrl;
            }
        } finally {
            foreach ($inFlightComputations as $argusCacheInFlightComputation) {
                $argusCacheInFlightComputation->releaseComputation();
            }
        }

        $urlIndexInspections = new UrlIndexInspections();
        $urlIndexInspections->siteKey = $insightsSite->key;
        $urlIndexInspections->provider = self::PROVIDER;
        $urlIndexInspections->property = $insightsSite->property;
        if ($languageCode !== null) {
            $urlIndexInspections->languageCode = $languageCode;
        }
        $indexingCoverage = new IndexingCoverage();
        $indexingCoverage->cacheLifetimeSeconds = $lifetimeSeconds;
        $indexingCoverage->inspectionsCounted = $inspectionsCounted;
        foreach ($argusInspectionsByUrl as $inspectionUrl => $argusGoogleUrlInspection) {
            $inspectionUrl = (string)$inspectionUrl;
            $answeredInspection = $answeredInspectionsByUrl[$inspectionUrl];
            $fromCache = $answeredFromCacheByUrl[$inspectionUrl] ?? false;
            $urlIndexInspection = $this->mapUrlInspection($answeredInspection, $inspectionUrl, $fromCache);
            $indexingCoverage->requestedParts++;
            if ($fromCache) {
                $indexingCoverage->partsFromCache++;
            } elseif ($answeredInspection instanceof ArgusGoogleUrlInspection) {
                $indexingCoverage->providerCalls += $answeredInspection->getProviderCallAttempts();
            }
            if (isset($urlIndexInspection->error)) {
                $indexingCoverage->failedParts++;
            } elseif (isset($urlIndexInspection->inspectedAt)) {
                $indexingCoverage->addAnsweredAt($urlIndexInspection->inspectedAt);
            }
            $urlIndexInspections->add($urlIndexInspection);
        }
        $indexingCoverage->fromCache = $indexingCoverage->requestedParts > 0 && $indexingCoverage->partsFromCache === $indexingCoverage->requestedParts;
        $urlIndexInspections->coverage = $indexingCoverage;
        return $urlIndexInspections;
    }

    /**
     * @param GoogleSitemaps $googleSitemaps
     * @param InsightsSite $insightsSite
     * @param bool $fromCache
     * @param int $providerCalls
     * @param int $lifetimeSeconds
     * @return SitemapStatuses Google's files as neutral states (submitted counts summed per file), or the error
     */
    public function mapSitemaps(GoogleSitemaps $googleSitemaps, InsightsSite $insightsSite, bool $fromCache, int $providerCalls, int $lifetimeSeconds): SitemapStatuses
    {
        $sitemapStatuses = new SitemapStatuses();
        $sitemapStatuses->siteKey = $insightsSite->key;
        $sitemapStatuses->provider = self::PROVIDER;
        $sitemapStatuses->property = $insightsSite->property;
        $indexingCoverage = new IndexingCoverage();
        $indexingCoverage->requestedParts = 1;
        $indexingCoverage->providerCalls = $providerCalls;
        $indexingCoverage->cacheLifetimeSeconds = $lifetimeSeconds;
        $sitemapStatuses->coverage = $indexingCoverage;
        if (isset($googleSitemaps->failure) || !$googleSitemaps->hasAnswer()) {
            $indexingCoverage->failedParts = 1;
            $sitemapStatuses->error = $this->createPartError($googleSitemaps->failure ?? null);
            return $sitemapStatuses;
        }
        $indexingCoverage->fromCache = $fromCache;
        $indexingCoverage->partsFromCache = $fromCache ? 1 : 0;
        $indexingCoverage->addAnsweredAt($googleSitemaps->fetchedAt);
        foreach (($googleSitemaps->sitemaps ?? null)?->getElements() ?? [] as $googleSitemapEntry) {
            $sitemapStatus = new SitemapStatus();
            $sitemapStatus->path = $googleSitemapEntry->path;
            if (isset($googleSitemapEntry->type)) {
                $sitemapStatus->type = $googleSitemapEntry->type;
            }
            $sitemapStatus->isIndex = $googleSitemapEntry->isSitemapsIndex;
            $sitemapStatus->isPending = $googleSitemapEntry->isPending;
            if (isset($googleSitemapEntry->lastSubmitted)) {
                $sitemapStatus->lastSubmitted = $googleSitemapEntry->lastSubmitted;
            }
            if (isset($googleSitemapEntry->lastDownloaded)) {
                $sitemapStatus->lastDownloaded = $googleSitemapEntry->lastDownloaded;
            }
            $sitemapStatus->warnings = $googleSitemapEntry->warnings;
            $sitemapStatus->errors = $googleSitemapEntry->errors;
            $sitemapStatus->submittedByType = new SitemapContentCounts();
            foreach (($googleSitemapEntry->contents ?? null)?->getElements() ?? [] as $googleSitemapContent) {
                $sitemapContentCount = new SitemapContentCount();
                $sitemapContentCount->type = $googleSitemapContent->type;
                $sitemapContentCount->submitted = $googleSitemapContent->submitted;
                $sitemapStatus->submittedByType->add($sitemapContentCount);
                $sitemapStatus->submitted += $googleSitemapContent->submitted;
            }
            $sitemapStatuses->add($sitemapStatus);
        }
        return $sitemapStatuses;
    }

    /**
     * @param GoogleUrlInspection $googleUrlInspection
     * @param string $inspectionUrl the URL as asked
     * @param bool $fromCache
     * @return UrlIndexInspection Google's index state as a neutral one, or the URL's error
     */
    public function mapUrlInspection(GoogleUrlInspection $googleUrlInspection, string $inspectionUrl, bool $fromCache): UrlIndexInspection
    {
        if (isset($googleUrlInspection->failure) || !$googleUrlInspection->hasAnswer()) {
            return UrlIndexInspection::createFailed($inspectionUrl, $this->createPartError($googleUrlInspection->failure ?? null));
        }
        $urlIndexInspection = new UrlIndexInspection();
        $urlIndexInspection->url = $inspectionUrl;
        foreach (['verdict', 'coverageState', 'indexingState', 'robotsTxtState', 'pageFetchState', 'lastCrawlTime', 'crawledAs', 'googleCanonical', 'userCanonical', 'inspectionResultLink'] as $propertyName) {
            if (isset($googleUrlInspection->$propertyName)) {
                $urlIndexInspection->$propertyName = $googleUrlInspection->$propertyName;
            }
        }
        $urlIndexInspection->sitemaps = $googleUrlInspection->sitemaps;
        $urlIndexInspection->referringUrls = $googleUrlInspection->referringUrls;
        $urlIndexInspection->inspectedAt = $googleUrlInspection->fetchedAt;
        $urlIndexInspection->fromCache = $fromCache;
        return $urlIndexInspection;
    }

    /**
     * @param GoogleApiFailure|null $googleApiFailure
     * @return SearchPartError the failure as a part error (providerFailed when Google gave no answer and no failure)
     */
    protected function createPartError(?GoogleApiFailure $googleApiFailure): SearchPartError
    {
        if ($googleApiFailure === null) {
            return SearchPartError::create(SiteInsightsErrorCause::PROVIDER_FAILED, 'no answer');
        }
        return SearchPartError::create($googleApiFailure->getCause(), $googleApiFailure->message, $googleApiFailure->httpStatus ?? null);
    }

    /**
     * @param InsightsSite $insightsSite
     * @return ArgusGoogleSitemaps the sitemaps question with the configured lifetime
     */
    protected function buildArgusSitemaps(InsightsSite $insightsSite): ArgusGoogleSitemaps
    {
        $argusGoogleSitemaps = new ArgusGoogleSitemaps();
        $argusGoogleSitemaps->credentialsEntryName = $insightsSite->credentialsEntryName;
        $argusGoogleSitemaps->credentialsRevision = $insightsSite->credentialsRevision;
        $argusGoogleSitemaps->siteUrl = $insightsSite->property;
        $argusGoogleSitemaps->getArgusSettings()->argusLoad->cacheTtl = $this->getSiteInsightsConfigService()->getCacheConfig()->sitemapsTtlSeconds;
        return $argusGoogleSitemaps;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $inspectionUrl
     * @param string|null $languageCode
     * @return ArgusGoogleUrlInspection the inspection question with the configured lifetime
     */
    protected function buildArgusUrlInspection(InsightsSite $insightsSite, string $inspectionUrl, ?string $languageCode): ArgusGoogleUrlInspection
    {
        $argusGoogleUrlInspection = new ArgusGoogleUrlInspection();
        $argusGoogleUrlInspection->credentialsEntryName = $insightsSite->credentialsEntryName;
        $argusGoogleUrlInspection->credentialsRevision = $insightsSite->credentialsRevision;
        $argusGoogleUrlInspection->siteUrl = $insightsSite->property;
        $argusGoogleUrlInspection->inspectionUrl = $inspectionUrl;
        if ($languageCode !== null) {
            $argusGoogleUrlInspection->languageCode = $languageCode;
        }
        $argusGoogleUrlInspection->getArgusSettings()->argusLoad->cacheTtl = $this->getSiteInsightsConfigService()->getCacheConfig()->inspectionTtlSeconds;
        return $argusGoogleUrlInspection;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $inspectionUrl
     * @param string|null $languageCode
     * @param string|null $cachedEntry
     * @param int $lifetimeSeconds
     * @return ArgusGoogleUrlInspection|null the cached inspection when it is an answer and younger than its lifetime,
     *         else null (a miss)
     */
    protected function hydrateFreshCachedInspection(InsightsSite $insightsSite, string $inspectionUrl, ?string $languageCode, ?string $cachedEntry, int $lifetimeSeconds): ?ArgusGoogleUrlInspection
    {
        if ($cachedEntry === null || $cachedEntry === '') {
            return null;
        }
        $argusGoogleUrlInspection = $this->buildArgusUrlInspection($insightsSite, $inspectionUrl, $languageCode);
        $argusGoogleUrlInspection->argusLoadFromCache($cachedEntry);
        if (!$argusGoogleUrlInspection->hasAnswer() || $argusGoogleUrlInspection->isOlderThan($lifetimeSeconds, $this->getFreshnessCheckTimestamp())) {
            return null;
        }
        return $argusGoogleUrlInspection;
    }

    /**
     * One call round of inspections in settle mode, reserved against the inspection quota in one step. When Argus
     * throws for the whole batch, only the parts without an answer are asked once more alone (each reserved again);
     * a part that fails again alone gets `answerUnreadable`.
     *
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleUrlInspections $argusGoogleUrlInspections
     * @return int inspections reserved against the quota
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    protected function loadInspectionBatch(InsightsSite $insightsSite, ArgusGoogleUrlInspections $argusGoogleUrlInspections): int
    {
        $partCount = count($argusGoogleUrlInspections->getElements());
        if (!$this->reserveInspectionsOrMarkRefused($insightsSite, $argusGoogleUrlInspections, $partCount)) {
            return 0;
        }
        $inspectionsReserved = $partCount;
        try {
            $this->executeInspectionBatchLoad($argusGoogleUrlInspections);
        } catch (RuntimeException $runtimeException) {
            foreach ($argusGoogleUrlInspections->getElements() as $argusGoogleUrlInspection) {
                if ($argusGoogleUrlInspection->getArgusSettings()->isLoaded) {
                    // answered (or failed on its own) before the batch threw: asking again would count it twice
                    continue;
                }
                $singlePartBatch = new ArgusGoogleUrlInspections();
                $singlePartBatch->add($argusGoogleUrlInspection);
                if (!$this->reserveInspectionsOrMarkRefused($insightsSite, $singlePartBatch, 1)) {
                    continue;
                }
                $inspectionsReserved++;
                try {
                    $this->executeInspectionBatchLoad($singlePartBatch);
                } catch (RuntimeException $singlePartRuntimeException) {
                    $argusGoogleUrlInspection->failure = $this->createFailure(
                        SiteInsightsErrorCause::ANSWER_UNREADABLE,
                        'the answer could not be read within its byte limit'
                    );
                }
            }
        }
        return $inspectionsReserved;
    }

    /**
     * After a 401 the cached token is dropped and the affected inspections are asked once more; a second 401 stays as
     * the inspection's failure (`tokenRefused`).
     *
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleUrlInspections $argusGoogleUrlInspections
     * @return int inspections reserved against the quota for the second round
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function retryUnauthorizedInspections(InsightsSite $insightsSite, ArgusGoogleUrlInspections $argusGoogleUrlInspections): int
    {
        $unauthorizedParts = new ArgusGoogleUrlInspections();
        foreach ($argusGoogleUrlInspections->getElements() as $argusGoogleUrlInspection) {
            if (($argusGoogleUrlInspection->failure->httpStatus ?? null) === 401) {
                unset($argusGoogleUrlInspection->failure);
                $argusGoogleUrlInspection->getArgusSettings()->isLoaded = false;
                $unauthorizedParts->add($argusGoogleUrlInspection);
            }
        }
        if (!count($unauthorizedParts->getElements())) {
            return 0;
        }
        $this->getSiteInsightsCredentialsProviderService()->forgetAccessToken($insightsSite->credentialsEntryName);
        return $this->loadInspectionBatch($insightsSite, $unauthorizedParts);
    }

    /**
     * @param ArgusGoogleUrlInspections $argusGoogleUrlInspections
     * @return void
     */
    protected function executeInspectionBatchLoad(ArgusGoogleUrlInspections $argusGoogleUrlInspections): void
    {
        $argusGoogleUrlInspections->setPropertiesToLoad(ArgusGoogleUrlInspection::class);
        $argusSettings = $argusGoogleUrlInspections->getArgusSettings();
        $argusSettings->keepFulfilledCallsWhenOneIsRejected = true;
        $argusSettings->timeout = (float)$this->getSiteInsightsConfigService()->getBudgetsConfig()->batchTimeoutSeconds;
        $argusGoogleUrlInspections->argusLoad(useArgusEntityCache: false, autoloadCurrentObject: false);
    }

    /**
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleUrlInspections $argusGoogleUrlInspections
     * @param int $inspections
     * @return bool false: the inspection budget refused them and every part carries `budgetRefused`
     */
    protected function reserveInspectionsOrMarkRefused(InsightsSite $insightsSite, ArgusGoogleUrlInspections $argusGoogleUrlInspections, int $inspections): bool
    {
        try {
            $this->getSiteInsightsProviderBudgetsService()->reserve($insightsSite, SiteInsightsProviderBudgetsService::QUOTA_INSPECTION, $inspections);
        } catch (SiteInsightsBudgetExceededException $siteInsightsBudgetExceededException) {
            foreach ($argusGoogleUrlInspections->getElements() as $argusGoogleUrlInspection) {
                $argusGoogleUrlInspection->failure = $this->createFailure(
                    SiteInsightsErrorCause::BUDGET_REFUSED,
                    $siteInsightsBudgetExceededException->getMessage()
                );
            }
            return false;
        }
        return true;
    }

    /**
     * One call round in settle mode with the batch timeout. When Argus throws for the whole batch (an oversized,
     * stalled or incomplete private answer), each part is asked once more alone; a part that fails again alone gets
     * `answerUnreadable`.
     *
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports
     * @return void
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    protected function loadBatch(InsightsSite $insightsSite, ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports): void
    {
        $partCount = count($argusGoogleSearchAnalyticsReports->getElements());
        if (!$this->reserveCallsOrMarkRefused($insightsSite, $argusGoogleSearchAnalyticsReports, $partCount)) {
            return;
        }
        try {
            $this->executeBatchLoad($argusGoogleSearchAnalyticsReports);
        } catch (RuntimeException $runtimeException) {
            foreach ($argusGoogleSearchAnalyticsReports->getElements() as $argusGoogleSearchAnalyticsReport) {
                $singlePartBatch = new ArgusGoogleSearchAnalyticsReports();
                $argusGoogleSearchAnalyticsReport->getArgusSettings()->isLoaded = false;
                $singlePartBatch->add($argusGoogleSearchAnalyticsReport);
                if (!$this->reserveCallsOrMarkRefused($insightsSite, $singlePartBatch, 1)) {
                    continue;
                }
                try {
                    $this->executeBatchLoad($singlePartBatch);
                } catch (RuntimeException $singlePartRuntimeException) {
                    $argusGoogleSearchAnalyticsReport->failure = $this->createFailure(
                        SiteInsightsErrorCause::ANSWER_UNREADABLE,
                        'the answer could not be read within its byte limit'
                    );
                }
            }
        }
    }

    /**
     * After a 401 the cached token is dropped and the affected parts are asked once more; a second 401 stays as the
     * part's failure (`tokenRefused`).
     *
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports
     * @return void
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function retryUnauthorizedParts(InsightsSite $insightsSite, ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports): void
    {
        $unauthorizedParts = new ArgusGoogleSearchAnalyticsReports();
        foreach ($argusGoogleSearchAnalyticsReports->getElements() as $argusGoogleSearchAnalyticsReport) {
            if (($argusGoogleSearchAnalyticsReport->failure->httpStatus ?? null) === 401) {
                unset($argusGoogleSearchAnalyticsReport->failure);
                $argusGoogleSearchAnalyticsReport->getArgusSettings()->isLoaded = false;
                $unauthorizedParts->add($argusGoogleSearchAnalyticsReport);
            }
        }
        if (!count($unauthorizedParts->getElements())) {
            return;
        }
        $this->getSiteInsightsCredentialsProviderService()->forgetAccessToken($insightsSite->credentialsEntryName);
        $this->loadBatch($insightsSite, $unauthorizedParts);
    }

    /**
     * @param ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports
     * @return void
     */
    protected function executeBatchLoad(ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports): void
    {
        $argusGoogleSearchAnalyticsReports->setPropertiesToLoad(ArgusGoogleSearchAnalyticsReport::class);
        $argusSettings = $argusGoogleSearchAnalyticsReports->getArgusSettings();
        $argusSettings->keepFulfilledCallsWhenOneIsRejected = true;
        $argusSettings->timeout = (float)$this->getSiteInsightsConfigService()->getBudgetsConfig()->batchTimeoutSeconds;
        $argusGoogleSearchAnalyticsReports->argusLoad(useArgusEntityCache: false, autoloadCurrentObject: false);
    }

    /**
     * @param InsightsSite $insightsSite
     * @param ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports
     * @param int $calls
     * @return bool false: the budget refused the calls and every part carries `budgetRefused`
     */
    protected function reserveCallsOrMarkRefused(InsightsSite $insightsSite, ArgusGoogleSearchAnalyticsReports $argusGoogleSearchAnalyticsReports, int $calls): bool
    {
        try {
            $this->getSiteInsightsProviderBudgetsService()->reserve($insightsSite, SiteInsightsProviderBudgetsService::QUOTA_SEARCH_ANALYTICS, $calls);
        } catch (SiteInsightsBudgetExceededException $siteInsightsBudgetExceededException) {
            foreach ($argusGoogleSearchAnalyticsReports->getElements() as $argusGoogleSearchAnalyticsReport) {
                $argusGoogleSearchAnalyticsReport->failure = $this->createFailure(
                    SiteInsightsErrorCause::BUDGET_REFUSED,
                    $siteInsightsBudgetExceededException->getMessage()
                );
            }
            return false;
        }
        return true;
    }

    /**
     * A single Argus load (sites, site, sitemaps, inspection) in settle mode with `budgets.singleCallTimeoutSeconds`.
     *
     * @param InsightsSite $insightsSite
     * @param GoogleProviderAnswer $argusAnswer an Argus class of a Google answer
     * @param string $quotaKind
     * @param bool $useCache
     * @param int $lifetimeSeconds the module's own freshness limit of a cache hit (0: no cache)
     * @return int provider calls made (0 for a fresh cache hit or a refused budget)
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    protected function loadSingleAnswer(InsightsSite $insightsSite, GoogleProviderAnswer $argusAnswer, string $quotaKind, bool $useCache, int $lifetimeSeconds): int
    {
        /** @var ArgusGoogleSites|ArgusGoogleSite|ArgusGoogleSitemaps|ArgusGoogleUrlInspection $argusAnswer */
        $argusSettings = $argusAnswer->getArgusSettings();
        $argusSettings->keepFulfilledCallsWhenOneIsRejected = true;
        $argusSettings->timeout = (float)$this->getSiteInsightsConfigService()->getBudgetsConfig()->singleCallTimeoutSeconds;
        if ($useCache) {
            // the cache is read like the batches read it: an Argus load with its entity cache would ask the provider
            // on a miss, outside the budget, before the counted call below
            $cacheKey = $argusAnswer->cacheKey();
            $cachedEntries = $this->readCachedEntries([$cacheKey => $argusSettings->argusLoad->cacheLevel], [$cacheKey => $lifetimeSeconds]);
            $cachedEntry = $cachedEntries[$cacheKey] ?? null;
            if ($cachedEntry !== null) {
                $argusAnswerClass = $argusAnswer::class;
                $cachedAnswer = new $argusAnswerClass();
                $cachedAnswer->argusLoadFromCache($cachedEntry);
                if ($cachedAnswer->hasAnswer() && !$cachedAnswer->isOlderThan($lifetimeSeconds, $this->getFreshnessCheckTimestamp())) {
                    $argusAnswer->argusLoadFromCache($cachedEntry);
                    return 0;
                }
            }
        }
        $providerCalls = 0;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->getSiteInsightsProviderBudgetsService()->reserve($insightsSite, $quotaKind, 1);
            } catch (SiteInsightsBudgetExceededException $siteInsightsBudgetExceededException) {
                $argusAnswer->failure = $this->createFailure(SiteInsightsErrorCause::BUDGET_REFUSED, $siteInsightsBudgetExceededException->getMessage());
                return $providerCalls;
            }
            $providerCalls++;
            $argusSettings->isLoaded = false;
            try {
                $argusAnswer->argusLoad(useArgusEntityCache: false);
            } catch (RuntimeException $runtimeException) {
                $argusAnswer->failure = $this->createFailure(SiteInsightsErrorCause::ANSWER_UNREADABLE, 'the answer could not be read within its byte limit');
                return $providerCalls;
            }
            if ($attempt === 1 && ($argusAnswer->failure->httpStatus ?? null) === 401) {
                unset($argusAnswer->failure);
                $this->getSiteInsightsCredentialsProviderService()->forgetAccessToken($insightsSite->credentialsEntryName);
                continue;
            }
            return $providerCalls;
        }
        return $providerCalls;
    }

    /**
     * Reads cache entries in one go: memory first for the memory levels, then one Redis read for the rest (a Redis
     * hit is copied into memory only for the memory-and-Redis level, with the entity's lifetime).
     *
     * @param int[] $cacheLevelsByCacheKey ArgusCache level per cache key
     * @param int[] $cacheTtlsByCacheKey lifetime per cache key
     * @return string[] cache entries by cache key
     */
    protected function readCachedEntries(array $cacheLevelsByCacheKey, array $cacheTtlsByCacheKey): array
    {
        $cachedEntriesByCacheKey = [];
        $redisCacheKeys = [];
        $memoryCopyTtlsByCacheKey = [];
        foreach ($cacheLevelsByCacheKey as $cacheKey => $cacheLevel) {
            $cacheKey = (string)$cacheKey;
            if ($cacheLevel === ArgusCache::CACHELEVEL_MEMORY_AND_DB) {
                $memoryCacheItem = ArgusCache::get($cacheKey, ArgusCache::CACHELEVEL_MEMORY);
                if ($memoryCacheItem !== null && is_string($memoryCacheItem->data) && $memoryCacheItem->data !== '') {
                    $cachedEntriesByCacheKey[$cacheKey] = $memoryCacheItem->data;
                    continue;
                }
                $memoryCopyTtlsByCacheKey[$cacheKey] = (int)($cacheTtlsByCacheKey[$cacheKey] ?? 0);
            }
            $redisCacheKeys[] = $cacheKey;
        }
        if ($redisCacheKeys) {
            foreach (ArgusCache::getMulti($redisCacheKeys, $memoryCopyTtlsByCacheKey) ?? [] as $cacheKey => $cachedEntry) {
                if (is_string($cachedEntry) && $cachedEntry !== '') {
                    $cachedEntriesByCacheKey[(string)$cacheKey] = $cachedEntry;
                }
            }
        }
        return $cachedEntriesByCacheKey;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param SearchQuestion $searchQuestion
     * @param string|null $cachedEntry
     * @param int $lifetimeSeconds
     * @return ArgusGoogleSearchAnalyticsReport|null the cached answer when it is an answer and younger than the
     *         question's lifetime (the module's own freshness check), else null (a miss)
     */
    protected function hydrateFreshCachedReport(InsightsSite $insightsSite, SearchQuestion $searchQuestion, ?string $cachedEntry, int $lifetimeSeconds): ?ArgusGoogleSearchAnalyticsReport
    {
        if ($cachedEntry === null || $cachedEntry === '') {
            return null;
        }
        $argusReport = $this->buildArgusReport($insightsSite, $searchQuestion);
        $argusReport->argusLoadFromCache($cachedEntry);
        if (!$argusReport->hasAnswer() || !isset($argusReport->rows) || $argusReport->isOlderThan($lifetimeSeconds, $this->getFreshnessCheckTimestamp())) {
            return null;
        }
        return $argusReport;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param SearchQuestion $searchQuestion
     * @return ArgusGoogleSearchAnalyticsReport the question as Google's question, with cache level and lifetime
     */
    protected function buildArgusReport(InsightsSite $insightsSite, SearchQuestion $searchQuestion): ArgusGoogleSearchAnalyticsReport
    {
        $argusReport = new ArgusGoogleSearchAnalyticsReport();
        $argusReport->credentialsEntryName = $insightsSite->credentialsEntryName;
        $argusReport->credentialsRevision = $insightsSite->credentialsRevision;
        $argusReport->siteUrl = $insightsSite->property;
        $argusReport->startDate = SearchDays::toDate(SearchDays::fromDate($searchQuestion->startDate));
        $argusReport->endDate = SearchDays::toDate(SearchDays::fromDate($searchQuestion->endDate));
        $argusReport->dimensions = array_values(array_unique($searchQuestion->dimensions));
        $argusReport->searchType = $searchQuestion->searchType;
        $argusReport->dataState = $searchQuestion->dataState;
        $argusReport->aggregationType = $searchQuestion->aggregationType;
        $argusReport->rowLimit = $searchQuestion->rowLimit;
        $argusReport->startRow = $searchQuestion->startRow;
        $googleSearchAnalyticsFilters = new GoogleSearchAnalyticsFilters();
        foreach ($searchQuestion->getSortedFilters()->getElements() as $searchQuestionFilter) {
            $googleSearchAnalyticsFilter = GoogleSearchAnalyticsFilter::create($searchQuestionFilter->dimension, $searchQuestionFilter->operator, $searchQuestionFilter->expression);
            $googleSearchAnalyticsFilters->add($googleSearchAnalyticsFilter);
        }
        $argusReport->filters = $googleSearchAnalyticsFilters;
        $argusLoad = $argusReport->getArgusSettings()->argusLoad;
        $argusLoad->cacheLevel = $this->determineCacheLevel($searchQuestion);
        $argusLoad->cacheTtl = $this->determineCacheLifetimeSeconds($searchQuestion);
        return $argusReport;
    }

    /**
     * @param GoogleSearchAnalyticsReport $googleSearchAnalyticsReport
     * @param SearchQuestion $searchQuestion
     * @param bool $fromCache
     * @return SearchReport
     */
    protected function buildSearchReport(GoogleSearchAnalyticsReport $googleSearchAnalyticsReport, SearchQuestion $searchQuestion, bool $fromCache): SearchReport
    {
        $searchReport = new SearchReport();
        $searchReport->question = $searchQuestion;
        $searchReport->performanceRows = $this->mapRows($googleSearchAnalyticsReport, $searchQuestion, $fromCache);
        $dateIndex = array_search('date', $googleSearchAnalyticsReport->getCanonicalDimensions(), true);
        if ($dateIndex !== false && isset($googleSearchAnalyticsReport->rows) && !isset($googleSearchAnalyticsReport->failure)) {
            $latestReportedDay = null;
            foreach ($googleSearchAnalyticsReport->rows->getElements() as $googleSearchAnalyticsRow) {
                $reportedDay = (string)($googleSearchAnalyticsRow->keys[$dateIndex] ?? '');
                if (SearchDays::isValidDay($reportedDay)) {
                    $latestReportedDay = $latestReportedDay === null ? $reportedDay : SearchDays::laterDay($latestReportedDay, $reportedDay);
                }
            }
            if ($latestReportedDay !== null) {
                $searchReport->latestReportedDay = $latestReportedDay;
            }
        }
        if (isset($googleSearchAnalyticsReport->firstIncompleteDate)) {
            $searchReport->firstIncompleteDay = SearchDays::fromDate($googleSearchAnalyticsReport->firstIncompleteDate);
        }
        return $searchReport;
    }

    /**
     * @param string|null $responseAggregationType Google's `responseAggregationType` (BY_PROPERTY, byPage, …)
     * @return string|null byProperty or byPage; null when Google named none or another
     */
    public static function mapResponseAggregationType(?string $responseAggregationType): ?string
    {
        return match (strtolower(str_replace('_', '', (string)$responseAggregationType))) {
            'byproperty' => SearchMetrics::AGGREGATION_BY_PROPERTY,
            'bypage' => SearchMetrics::AGGREGATION_BY_PAGE,
            default => null,
        };
    }

    /**
     * @param string $requestedAggregation
     * @param string[] $canonicalDimensions
     * @param string[] $filterDimensions
     * @return string the aggregation Google applies to a request when it names none: byPage when asked or when the
     *         question groups or filters by page under `auto`, else byProperty
     */
    protected function determineAggregationOfRequest(string $requestedAggregation, array $canonicalDimensions, array $filterDimensions): string
    {
        if ($requestedAggregation === SearchQuestion::AGGREGATION_BY_PAGE
            || ($requestedAggregation === SearchQuestion::AGGREGATION_AUTO
                && (in_array('page', $canonicalDimensions, true) || in_array('page', $filterDimensions, true)))
        ) {
            return SearchMetrics::AGGREGATION_BY_PAGE;
        }
        return SearchMetrics::AGGREGATION_BY_PROPERTY;
    }

    /**
     * @param SearchQuestion $searchQuestion
     * @return int seconds: probe `probeTtlSeconds`; final data within the final days `finalWindowTtlSeconds`;
     *         everything else (all, or a window reaching beyond the last final day or unknown) `freshWindowTtlSeconds`
     */
    protected function determineCacheLifetimeSeconds(SearchQuestion $searchQuestion): int
    {
        $cacheConfig = $this->getSiteInsightsConfigService()->getCacheConfig();
        if ($searchQuestion->isAvailabilityProbe) {
            return $cacheConfig->probeTtlSeconds;
        }
        if ($searchQuestion->dataState === SearchWindowRequest::DATA_STATE_FINAL
            && isset($searchQuestion->dataThroughDate)
            && strcmp(SearchDays::fromDate($searchQuestion->endDate), SearchDays::fromDate($searchQuestion->dataThroughDate)) <= 0
        ) {
            return $cacheConfig->finalWindowTtlSeconds;
        }
        return $cacheConfig->freshWindowTtlSeconds;
    }

    /**
     * @param SearchQuestion $searchQuestion
     * @return int totals and series (small) in memory and Redis; row lists in Redis only; nothing for an export
     */
    protected function determineCacheLevel(SearchQuestion $searchQuestion): int
    {
        if (!$searchQuestion->cacheable) {
            return ArgusCache::CACHELEVEL_NONE;
        }
        $dimensions = array_values(array_unique($searchQuestion->dimensions));
        if ($dimensions === [] || $dimensions === ['date']) {
            return ArgusCache::CACHELEVEL_MEMORY_AND_DB;
        }
        return ArgusCache::CACHELEVEL_DB;
    }

    /**
     * @param string $searchType
     * @param string $dataState
     * @param string $today
     * @return SearchQuestion dates of the last ten days, by property
     */
    protected function buildProbeQuestion(string $searchType, string $dataState, string $today): SearchQuestion
    {
        $searchQuestion = new SearchQuestion();
        $searchQuestion->startDate = SearchDays::toDate(SearchDays::addDays($today, -self::PROBE_DAYS));
        $searchQuestion->endDate = SearchDays::toDate($today);
        $searchQuestion->dimensions = ['date'];
        $searchQuestion->searchType = $searchType;
        $searchQuestion->dataState = $dataState;
        $searchQuestion->aggregationType = SearchQuestion::AGGREGATION_BY_PROPERTY;
        $searchQuestion->rowLimit = self::PROBE_DAYS + 1;
        $searchQuestion->isAvailabilityProbe = true;
        return $searchQuestion;
    }

    /**
     * @param SearchQuestion $searchQuestion
     * @return void
     * @throws BadRequestException when a value is not one Google accepts
     */
    protected function validateQuestion(SearchQuestion $searchQuestion): void
    {
        if (!isset($searchQuestion->startDate, $searchQuestion->endDate)
            || strcmp(SearchDays::fromDate($searchQuestion->startDate), SearchDays::fromDate($searchQuestion->endDate)) > 0
        ) {
            throw new BadRequestException('A search question needs a start day on or before its end day');
        }
        foreach ($searchQuestion->dimensions as $dimension) {
            if (!in_array($dimension, self::DIMENSIONS, true)) {
                throw new BadRequestException('Unknown dimension "' . mb_substr((string)$dimension, 0, 40) . '"');
            }
        }
        foreach ($searchQuestion->getSortedFilters()->getElements() as $searchQuestionFilter) {
            if (!in_array($searchQuestionFilter->dimension, self::FILTER_DIMENSIONS, true) || !in_array($searchQuestionFilter->operator, self::FILTER_OPERATORS, true)) {
                throw new BadRequestException('Unknown filter "' . mb_substr($searchQuestionFilter->dimension . ' ' . $searchQuestionFilter->operator, 0, 60) . '"');
            }
        }
        if (!in_array($searchQuestion->searchType, self::SEARCH_TYPES, true)
            || !in_array($searchQuestion->dataState, self::DATA_STATES, true)
            || !in_array($searchQuestion->aggregationType, self::AGGREGATIONS, true)
        ) {
            throw new BadRequestException('Unknown search type, data state or aggregation');
        }
        if ($searchQuestion->rowLimit < 1 || $searchQuestion->rowLimit > self::PROVIDER_MAX_ROW_LIMIT || $searchQuestion->startRow < 0) {
            throw new BadRequestException('Rows asked must be 1 to ' . self::PROVIDER_MAX_ROW_LIMIT . ', the first row 0 or more');
        }
    }

    /**
     * @param SiteInsightsErrorCause $siteInsightsErrorCause
     * @param string $message
     * @return GoogleApiFailure a failure the module itself determined (no provider status)
     */
    protected function createFailure(SiteInsightsErrorCause $siteInsightsErrorCause, string $message): GoogleApiFailure
    {
        $googleApiFailure = new GoogleApiFailure();
        $googleApiFailure->cause = $siteInsightsErrorCause->value;
        $googleApiFailure->message = GoogleApiFailure::shortenMessage($message);
        return $googleApiFailure;
    }

    /** Seam for tests: the moment that decides today in the provider's zone */
    protected function getNowTimestamp(): int
    {
        return time();
    }

    /** Seam for tests: the moment a cached answer's age is measured at (the clock `fetchedAt` was written with) */
    protected function getFreshnessCheckTimestamp(): int
    {
        return time();
    }

    protected function getSiteInsightsConfigService(): SiteInsightsConfigService
    {
        return DDDService::instance()->getService(SiteInsightsConfigService::class);
    }

    protected function getSiteInsightsProviderBudgetsService(): SiteInsightsProviderBudgetsService
    {
        return DDDService::instance()->getService(SiteInsightsProviderBudgetsService::class);
    }

    protected function getSiteInsightsCredentialsProviderService(): SiteInsightsCredentialsProviderService
    {
        return DDDService::instance()->getService(SiteInsightsCredentialsProviderService::class);
    }
}
