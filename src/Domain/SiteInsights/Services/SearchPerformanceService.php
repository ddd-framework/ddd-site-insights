<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsBudgetsConfig;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchDetailParts;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportDay;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportDays;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportSummary;
use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Entities\Search\SearchPageDetail;
use DDD\Domain\SiteInsights\Entities\Search\SearchPageGroupPerformance;
use DDD\Domain\SiteInsights\Entities\Search\SearchPageGroupPerformances;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceOverview;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchQueryDetail;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilter;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilters;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchReport;
use DDD\Domain\SiteInsights\Entities\Search\SearchReports;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchTotals;
use DDD\Domain\SiteInsights\Entities\Search\SearchTransportFailure;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Domain\SiteInsights\Utils\SearchRegex;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Search performance of a configured site: the overview, the lists by query, page, country and device with the
 * comparison window's counterpart per row, the page groups, one page or one query in depth, and the export.
 *
 * Every method asks the access policy first (before any cache read), builds the window from the provider's last
 * final day, and asks its distinct questions in as few batches as the request graph allows. Every answer carries its
 * window (provider days, data state, last final day), its comparison window (partial, clamped or refused) and the
 * coverage of every part; a part that failed carries its `error` instead of rows.
 */
class SearchPerformanceService extends Service
{
    /** @var int Rows of the countries list of the overview (Google knows about 250 countries) */
    public const int COUNTRIES_ROW_LIMIT = 250;

    /** @var int Rows of the devices list of the overview */
    public const int DEVICES_ROW_LIMIT = 5;

    /** @var int Rows of the one comparison call of a countries or devices list */
    public const int SMALL_LIST_COUNTERPART_ROW_LIMIT = 250;

    public const int QUERY_MAX_LENGTH = 400;
    public const int URL_MAX_LENGTH = 2048;
    public const string COUNTRY_PATTERN = '/^[a-z]{3}$/';
    public const string PAGE_GROUP_KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,62}$/';

    /**
     * Totals, series and top lists of one window in one batch (the probe before it is normally cached).
     *
     * @param string $siteKey
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchPerformanceOverview
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException when the overview plans more calls than it may
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function getOverview(string $siteKey, SearchWindowRequest $searchWindowRequest): SearchPerformanceOverview
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $searchWindow = $this->buildWindow($insightsSite, $searchWindowRequest);
        $searchComparisonWindow = $this->buildComparisonWindow($searchWindow, $searchWindowRequest->compare);
        $comparableWindow = $this->findComparableWindow($searchComparisonWindow);
        $noFilters = new SearchQuestionFilters();

        $totalsQuestion = $this->buildQuestion($searchWindow, [], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, 1);
        $seriesQuestion = $this->buildQuestion($searchWindow, ['date'], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, $searchWindow->days);
        $topQueriesQuestion = $this->buildQuestion($searchWindow, ['query'], $noFilters, SearchQuestion::AGGREGATION_AUTO, $budgetsConfig->overviewListSize);
        $topPagesQuestion = $this->buildQuestion($searchWindow, ['page'], $noFilters, SearchQuestion::AGGREGATION_AUTO, $budgetsConfig->overviewListSize);
        $countriesQuestion = $this->buildQuestion($searchWindow, ['country'], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, self::COUNTRIES_ROW_LIMIT);
        $devicesQuestion = $this->buildQuestion($searchWindow, ['device'], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, self::DEVICES_ROW_LIMIT);
        $searchQuestions = $this->createQuestionSet($totalsQuestion, $seriesQuestion, $topQueriesQuestion, $topPagesQuestion, $countriesQuestion, $devicesQuestion);
        $comparisonTotalsQuestion = null;
        $comparisonSeriesQuestion = null;
        if ($comparableWindow !== null) {
            $comparisonTotalsQuestion = $this->buildQuestion($comparableWindow, [], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, 1);
            $comparisonSeriesQuestion = $this->buildQuestion($comparableWindow, ['date'], $noFilters, SearchQuestion::AGGREGATION_BY_PROPERTY, $comparableWindow->days);
            $searchQuestions->add($comparisonTotalsQuestion, $comparisonSeriesQuestion);
        }
        $this->assertOperationBudget(SiteInsightsBudgetsConfig::OPERATION_OVERVIEW, $searchQuestions);
        $searchReports = $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $searchQuestions);

        $searchPerformanceOverview = new SearchPerformanceOverview();
        $searchPerformanceOverview->siteKey = $insightsSite->key;
        $searchPerformanceOverview->window = $searchWindow;
        if ($searchComparisonWindow !== null) {
            $searchPerformanceOverview->comparisonWindow = $searchComparisonWindow;
        }
        $searchPerformanceOverview->dataThroughDate = SearchDays::toDate(SearchDays::fromDate($searchWindow->dataThroughDate));
        $searchPerformanceOverview->dataThroughDateSource = $searchWindow->dataThroughDateSource ?? SearchWindow::DATA_THROUGH_DATE_SOURCE_FALLBACK;
        $searchPerformanceOverview->totals = $this->buildTotals(
            $this->findReport($searchReports, $totalsQuestion),
            $comparisonTotalsQuestion ? $this->findReport($searchReports, $comparisonTotalsQuestion) : null,
            $searchWindow,
            $comparableWindow
        );
        $searchPerformanceOverview->series = $this->buildSeries(
            $this->findReport($searchReports, $seriesQuestion),
            $comparisonSeriesQuestion ? $this->findReport($searchReports, $comparisonSeriesQuestion) : null,
            $searchWindow,
            $searchComparisonWindow
        );
        $searchPerformanceOverview->topQueries = $this->attachWindows($this->findReport($searchReports, $topQueriesQuestion)->performanceRows, $searchWindow, null);
        $searchPerformanceOverview->topPages = $this->attachWindows($this->findReport($searchReports, $topPagesQuestion)->performanceRows, $searchWindow, null);
        $searchPerformanceOverview->countries = $this->attachWindows($this->findReport($searchReports, $countriesQuestion)->performanceRows, $searchWindow, null);
        $searchPerformanceOverview->devices = $this->attachWindows($this->findReport($searchReports, $devicesQuestion)->performanceRows, $searchWindow, null);
        return $searchPerformanceOverview;
    }

    /**
     * One list grouped by query, page, country or device. Sorted by clicks descending: one call from `offset`, Google's
     * order (`sortScope` provider). Any other order: pages of `budgets.rowsPerCall` up to `budgets.sortFetchCeiling`,
     * sorted and sliced by the module (`sortScope` fetchedRows). With a comparison, each row gets its counterpart in
     * the comparison window, asked by key (a row without one is `notObserved`, never zero).
     *
     * @param string $siteKey
     * @param SearchListRequest $searchListRequest
     * @return SearchPerformanceRows
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    public function getRows(string $siteKey, SearchListRequest $searchListRequest): SearchPerformanceRows
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $this->validateListRequest($searchListRequest);
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $searchQuestionFilters = $this->buildListFilters($insightsSite, $searchListRequest);
        $searchWindow = $this->buildWindow($insightsSite, $searchListRequest);
        $searchComparisonWindow = $this->buildComparisonWindow($searchWindow, $searchListRequest->compare);
        $comparableWindow = $this->findComparableWindow($searchComparisonWindow);
        $dimension = $searchListRequest->dimension;
        $aggregationType = $this->determineListAggregation($dimension, $searchQuestionFilters);

        $plannedPages = $searchListRequest->isSortedByProvider() ? 1 : (int)ceil($budgetsConfig->sortFetchCeiling / max(1, $budgetsConfig->rowsPerCall));
        $plannedCounterpartCalls = $comparableWindow === null ? 0 : ($this->isSmallListDimension($dimension) ? 1 : $budgetsConfig->counterpartMaxChunks);
        $this->assertPlannedCalls(SiteInsightsBudgetsConfig::OPERATION_LIST, $plannedPages + $plannedCounterpartCalls + 1);

        if ($searchListRequest->isSortedByProvider()) {
            $listQuestion = $this->buildQuestion($searchWindow, [$dimension], $searchQuestionFilters, $aggregationType, $searchListRequest->limit, $searchListRequest->offset);
            $searchPerformanceRows = $this->findReport(
                $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $this->createQuestionSet($listQuestion)),
                $listQuestion
            )->performanceRows;
            $searchPerformanceRows->coverage->sortScope = SearchRowsCoverage::SORT_SCOPE_PROVIDER;
        } else {
            $searchPerformanceRows = $this->fetchAndSortRows($insightsSite, $searchWindow, $searchListRequest, $searchQuestionFilters, $aggregationType);
        }
        $this->attachWindows($searchPerformanceRows, $searchWindow, $searchComparisonWindow);
        if ($comparableWindow !== null && !isset($searchPerformanceRows->error)) {
            $this->addCounterparts($insightsSite, $searchPerformanceRows, $dimension, $searchQuestionFilters, $aggregationType, $searchWindow, $comparableWindow);
        }
        return $searchPerformanceRows;
    }

    /**
     * One aggregate per configured page group (its own question with the group's page expression, page aggregation),
     * at most `budgets.maxPageGroups` groups, with comparison. Groups may overlap and are never summed.
     *
     * @param string $siteKey
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchPageGroupPerformances
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    public function getPageGroups(string $siteKey, SearchWindowRequest $searchWindowRequest): SearchPageGroupPerformances
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $searchWindow = $this->buildWindow($insightsSite, $searchWindowRequest);
        $searchComparisonWindow = $this->buildComparisonWindow($searchWindow, $searchWindowRequest->compare);
        $comparableWindow = $this->findComparableWindow($searchComparisonWindow);

        $searchPageGroupPerformances = new SearchPageGroupPerformances();
        $searchPageGroupPerformances->siteKey = $insightsSite->key;
        $searchPageGroupPerformances->window = $searchWindow;
        if ($searchComparisonWindow !== null) {
            $searchPageGroupPerformances->comparisonWindow = $searchComparisonWindow;
        }
        $insightsPageGroups = array_values($insightsSite->pageGroups->getElements());
        $askedPageGroups = array_slice($insightsPageGroups, 0, max(0, $budgetsConfig->maxPageGroups));
        $searchPageGroupPerformances->pageGroupsLeftOut = count($insightsPageGroups) - count($askedPageGroups);
        if (!$askedPageGroups) {
            return $searchPageGroupPerformances;
        }

        // method-local: the questions of each group by group key
        $currentQuestionsByGroupKey = [];
        $comparisonQuestionsByGroupKey = [];
        $pageExpressionsByGroupKey = [];
        $searchQuestions = new SearchQuestions();
        foreach ($askedPageGroups as $insightsPageGroup) {
            $pageExpression = SearchRegex::buildPageGroupExpression($insightsSite, $insightsPageGroup);
            $pageExpressionsByGroupKey[$insightsPageGroup->key] = $pageExpression;
            $groupFilters = $this->createFilterSet(SearchQuestionFilter::create('page', 'includingRegex', $pageExpression));
            $currentQuestion = $this->buildQuestion($searchWindow, [], $groupFilters, SearchQuestion::AGGREGATION_AUTO, 1);
            $currentQuestionsByGroupKey[$insightsPageGroup->key] = $currentQuestion;
            $searchQuestions->add($currentQuestion);
            if ($comparableWindow !== null) {
                $comparisonQuestion = $this->buildQuestion($comparableWindow, [], $groupFilters, SearchQuestion::AGGREGATION_AUTO, 1);
                $comparisonQuestionsByGroupKey[$insightsPageGroup->key] = $comparisonQuestion;
                $searchQuestions->add($comparisonQuestion);
            }
        }
        $this->assertOperationBudget(SiteInsightsBudgetsConfig::OPERATION_PAGE_GROUPS, $searchQuestions);
        $searchReports = $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $searchQuestions);
        foreach ($askedPageGroups as $insightsPageGroup) {
            $searchPageGroupPerformance = new SearchPageGroupPerformance();
            $searchPageGroupPerformance->pageGroup = $insightsPageGroup;
            $searchPageGroupPerformance->pageExpression = $pageExpressionsByGroupKey[$insightsPageGroup->key];
            $comparisonQuestion = $comparisonQuestionsByGroupKey[$insightsPageGroup->key] ?? null;
            $searchPageGroupPerformance->totals = $this->buildTotals(
                $this->findReport($searchReports, $currentQuestionsByGroupKey[$insightsPageGroup->key]),
                $comparisonQuestion ? $this->findReport($searchReports, $comparisonQuestion) : null,
                $searchWindow,
                $comparableWindow
            );
            $searchPageGroupPerformances->add($searchPageGroupPerformance);
        }
        return $searchPageGroupPerformances;
    }

    /**
     * One page in depth: its metrics and series (page `equals`, page aggregation) with comparison, and the queries
     * it was shown for (`budgets.detailListSize` rows by clicks).
     *
     * @param string $siteKey
     * @param string $url a URL of the property
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchPageDetail
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException when the URL does not belong to the property
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    public function getPageDetail(string $siteKey, string $url, SearchWindowRequest $searchWindowRequest): SearchPageDetail
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $pageUrl = $this->normalizePageUrl($insightsSite, $url);
        $searchWindow = $this->buildWindow($insightsSite, $searchWindowRequest);
        $searchComparisonWindow = $this->buildComparisonWindow($searchWindow, $searchWindowRequest->compare);
        $pageFilters = $this->createFilterSet(SearchQuestionFilter::create('page', 'equals', $pageUrl));
        $queriesQuestion = $this->buildQuestion(
            $searchWindow,
            ['query'],
            $pageFilters,
            SearchQuestion::AGGREGATION_AUTO,
            $this->getSiteInsightsConfigService()->getBudgetsConfig()->detailListSize
        );

        $searchPageDetail = new SearchPageDetail();
        $searchPageDetail->siteKey = $insightsSite->key;
        $searchPageDetail->page = $pageUrl;
        /** @var InsightsSitesService $insightsSitesService */
        $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
        $insightsPageGroup = $insightsSitesService->findPageGroupOfUrl($insightsSite, $pageUrl);
        if ($insightsPageGroup !== null) {
            $searchPageDetail->pageGroup = $insightsPageGroup;
        }
        $searchPageDetail->window = $searchWindow;
        if ($searchComparisonWindow !== null) {
            $searchPageDetail->comparisonWindow = $searchComparisonWindow;
        }
        $detailParts = $this->loadDetailParts(
            $insightsSite,
            SiteInsightsBudgetsConfig::OPERATION_PAGE_DETAIL,
            $searchWindow,
            $searchComparisonWindow,
            $pageFilters,
            SearchQuestion::AGGREGATION_AUTO,
            $queriesQuestion
        );
        $searchPageDetail->totals = $detailParts->totals;
        $searchPageDetail->series = $detailParts->series;
        $searchPageDetail->queries = $detailParts->rows;
        return $searchPageDetail;
    }

    /**
     * One query in depth: its metrics and series (query `equals`, property aggregation) with comparison, and the
     * pages shown for it (`budgets.detailListSize` rows by clicks, page aggregation).
     *
     * @param string $siteKey
     * @param string $query the exact query
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchQueryDetail
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    public function getQueryDetail(string $siteKey, string $query, SearchWindowRequest $searchWindowRequest): SearchQueryDetail
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $trimmedQuery = trim($query);
        if ($trimmedQuery === '' || mb_strlen($trimmedQuery) > self::QUERY_MAX_LENGTH) {
            throw new BadRequestException('query must have 1 to ' . self::QUERY_MAX_LENGTH . ' characters');
        }
        $searchWindow = $this->buildWindow($insightsSite, $searchWindowRequest);
        $searchComparisonWindow = $this->buildComparisonWindow($searchWindow, $searchWindowRequest->compare);
        $queryFilters = $this->createFilterSet(SearchQuestionFilter::create('query', 'equals', $trimmedQuery));
        $pagesQuestion = $this->buildQuestion(
            $searchWindow,
            ['page'],
            $queryFilters,
            SearchQuestion::AGGREGATION_AUTO,
            $this->getSiteInsightsConfigService()->getBudgetsConfig()->detailListSize
        );

        $searchQueryDetail = new SearchQueryDetail();
        $searchQueryDetail->siteKey = $insightsSite->key;
        $searchQueryDetail->query = $trimmedQuery;
        $searchQueryDetail->window = $searchWindow;
        if ($searchComparisonWindow !== null) {
            $searchQueryDetail->comparisonWindow = $searchComparisonWindow;
        }
        $detailParts = $this->loadDetailParts(
            $insightsSite,
            SiteInsightsBudgetsConfig::OPERATION_QUERY_DETAIL,
            $searchWindow,
            $searchComparisonWindow,
            $queryFilters,
            SearchQuestion::AGGREGATION_BY_PROPERTY,
            $pagesQuestion
        );
        $searchQueryDetail->totals = $detailParts->totals;
        $searchQueryDetail->series = $detailParts->series;
        $searchQueryDetail->pages = $detailParts->rows;
        return $searchQueryDetail;
    }

    /**
     * Pages a report to its end within `budgets.callsPerOperation.export` calls: one window per day when asked by day,
     * pages of `budgets.maxRowsPerCall` rows until a page returns fewer rows than asked, sequentially, never cached.
     * Every row goes to `$writeRow` as it arrives (nothing is held); above the budget the export stops with
     * `limitReached` and names the last finished day. A page whose call fails with a retryable cause (transport,
     * provider, quota; not the module's own budget) is asked again up to `budgets.exportRetries` times after
     * `budgets.exportRetryBackoffMilliseconds` × the retry's number; retries count against the call budget. A part
     * that still fails, or fails with another cause, stops the export with `error` (attempts and, for a transport
     * failure, the measured `transportFailure`). `resumeAfter` continues a run after its `lastFinishedDay`.
     *
     * @param string $siteKey
     * @param SearchExportRequest $searchExportRequest
     * @param callable $writeRow receives each SearchPerformanceRow
     * @return SearchExportSummary
     * @throws ForbiddenException when the account may not export
     * @throws NotFoundException
     * @throws BadRequestException also for a resumeAfter outside the window
     * @throws InternalErrorException
     * @throws SiteInsightsProviderUnavailableException
     */
    public function export(string $siteKey, SearchExportRequest $searchExportRequest, callable $writeRow): SearchExportSummary
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $siteInsightsAccessPolicyService = $this->getSiteInsightsAccessPolicyService();
        if (!$siteInsightsAccessPolicyService->canExport($siteInsightsAccessPolicyService->requireViewer())) {
            throw new ForbiddenException('This account may not export site insights');
        }
        $dimensions = $this->validateExportDimensions($searchExportRequest->dimensions);
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $callLimit = (int)$budgetsConfig->getCallsAllowedForOperation(SiteInsightsBudgetsConfig::OPERATION_EXPORT);
        $rowLimit = min(GoogleSearchConsoleSourceService::PROVIDER_MAX_ROW_LIMIT, max(1, $budgetsConfig->maxRowsPerCall));
        $searchWindow = $this->buildWindow($insightsSite, $searchExportRequest);
        $aggregationType = in_array('page', $dimensions, true) ? SearchQuestion::AGGREGATION_AUTO : SearchQuestion::AGGREGATION_BY_PROPERTY;

        $searchExportSummary = new SearchExportSummary();
        $searchExportSummary->siteKey = $insightsSite->key;
        $searchExportSummary->window = $searchWindow;
        $searchExportSummary->dimensions = $dimensions;
        $searchExportSummary->byDay = $searchExportRequest->isAskedByDay();
        $searchExportSummary->anonymizedQueriesOmitted = in_array('query', $dimensions, true);
        $searchExportSummary->joinedDimensionsMayLoseRows = in_array('query', $dimensions, true) && in_array('page', $dimensions, true);
        $searchExportSummary->days = new SearchExportDays();

        $fromDay = SearchDays::fromDate($searchWindow->from);
        $toDay = SearchDays::fromDate($searchWindow->to);
        $firstDay = $fromDay;
        if (isset($searchExportRequest->resumeAfter)) {
            $resumeAfterDay = SearchDays::fromDate($searchExportRequest->resumeAfter);
            if (SearchDays::daysInclusive($fromDay, $resumeAfterDay) < 1 || SearchDays::daysInclusive($resumeAfterDay, $toDay) < 2) {
                throw new BadRequestException(sprintf(
                    'resumeAfter must be a day from %s to the day before %s (the window); %s given',
                    $fromDay,
                    $toDay,
                    $resumeAfterDay
                ));
            }
            $firstDay = SearchDays::addDays($resumeAfterDay, 1);
            $searchExportSummary->resumedAfter = SearchDays::toDate($resumeAfterDay);
        }
        $partCount = $searchExportSummary->byDay ? SearchDays::daysInclusive($firstDay, $toDay) : 1;
        for ($partIndex = 0; $partIndex < $partCount; $partIndex++) {
            $partFromDay = $searchExportSummary->byDay ? SearchDays::addDays($firstDay, $partIndex) : $firstDay;
            $partToDay = $searchExportSummary->byDay ? $partFromDay : $toDay;
            $searchExportDay = new SearchExportDay();
            $searchExportDay->from = SearchDays::toDate($partFromDay);
            $searchExportDay->to = SearchDays::toDate($partToDay);
            $searchExportSummary->days->add($searchExportDay);
            $partWindow = clone $searchWindow;
            $partWindow->from = SearchDays::toDate($partFromDay);
            $partWindow->to = SearchDays::toDate($partToDay);
            $partWindow->days = SearchDays::daysInclusive($partFromDay, $partToDay);
            $startRow = 0;
            $pageAttempts = 0;
            $lastPageError = null;
            while (true) {
                if ($searchExportSummary->providerCalls >= $callLimit) {
                    $searchExportDay->limitReached = true;
                    $searchExportSummary->limitReached = true;
                    if ($lastPageError !== null) {
                        // the budget ended the retries: the failure stays named
                        $searchExportDay->error = $lastPageError;
                        $searchExportSummary->error = $lastPageError;
                    }
                    return $searchExportSummary;
                }
                $exportQuestion = $this->buildQuestion($partWindow, $dimensions, new SearchQuestionFilters(), $aggregationType, $rowLimit, $startRow);
                $exportQuestion->cacheable = false;
                $pageAttempts++;
                $callStartedAtMilliseconds = $this->getMonotonicMilliseconds();
                $pageRows = $this->findReport(
                    $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $this->createQuestionSet($exportQuestion), false),
                    $exportQuestion
                )->performanceRows;
                $callElapsedMilliseconds = $this->getMonotonicMilliseconds() - $callStartedAtMilliseconds;
                $pageCalls = max(1, $pageRows->coverage->providerCalls);
                $searchExportDay->providerCalls += $pageCalls;
                $searchExportSummary->providerCalls += $pageCalls;
                if (isset($pageRows->error)) {
                    $lastPageError = $pageRows->error;
                    $lastPageError->attempts = $pageAttempts;
                    if ($lastPageError->getCause() === SiteInsightsErrorCause::TRANSPORT) {
                        $lastPageError->transportFailure = SearchTransportFailure::create($callElapsedMilliseconds, $budgetsConfig->batchTimeoutSeconds);
                    }
                    if ($this->isExportRetryable($lastPageError) && $pageAttempts <= $budgetsConfig->exportRetries) {
                        $searchExportDay->retries++;
                        $searchExportSummary->retries++;
                        $this->waitBeforeExportRetry($budgetsConfig->exportRetryBackoffMilliseconds * $pageAttempts);
                        continue;
                    }
                    $searchExportDay->error = $lastPageError;
                    $searchExportSummary->error = $lastPageError;
                    return $searchExportSummary;
                }
                $pageAttempts = 0;
                $lastPageError = null;
                foreach ($pageRows->getElements() as $searchPerformanceRow) {
                    $writeRow($searchPerformanceRow);
                }
                $pageRowCount = count($pageRows->getElements());
                $searchExportDay->fetchedRows += $pageRowCount;
                $searchExportSummary->rowsWritten += $pageRowCount;
                if ($pageRowCount < $rowLimit) {
                    $searchExportDay->providerRowsExhausted = true;
                    break;
                }
                $startRow += $rowLimit;
            }
            $searchExportSummary->lastFinishedDay = SearchDays::toDate($partToDay);
        }
        return $searchExportSummary;
    }

    /**
     * @param SearchPartError $searchPartError
     * @return bool whether an export asks the page again: a retryable cause, except the module's own budget (asking
     *         again at once would be refused again)
     */
    protected function isExportRetryable(SearchPartError $searchPartError): bool
    {
        return $searchPartError->retryable && $searchPartError->getCause() !== SiteInsightsErrorCause::BUDGET_REFUSED;
    }

    /** Seam for tests: waits before an export retry */
    protected function waitBeforeExportRetry(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /** Seam for tests: a monotonic clock in milliseconds (how long a failed call took) */
    protected function getMonotonicMilliseconds(): int
    {
        return (int)(hrtime(true) / 1000000);
    }

    /**
     * Totals and series of the window and the comparison window plus one list, in one batch.
     *
     * @param InsightsSite $insightsSite
     * @param string $operation
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow|null $searchComparisonWindow
     * @param SearchQuestionFilters $subjectFilters the filter naming the page or query
     * @param string $aggregationType of totals and series
     * @param SearchQuestion $listQuestion
     * @return SearchDetailParts
     * @throws BadRequestException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadDetailParts(
        InsightsSite $insightsSite,
        string $operation,
        SearchWindow $searchWindow,
        ?SearchComparisonWindow $searchComparisonWindow,
        SearchQuestionFilters $subjectFilters,
        string $aggregationType,
        SearchQuestion $listQuestion
    ): SearchDetailParts {
        $comparableWindow = $this->findComparableWindow($searchComparisonWindow);
        $totalsQuestion = $this->buildQuestion($searchWindow, [], $subjectFilters, $aggregationType, 1);
        $seriesQuestion = $this->buildQuestion($searchWindow, ['date'], $subjectFilters, $aggregationType, $searchWindow->days);
        $searchQuestions = $this->createQuestionSet($totalsQuestion, $seriesQuestion, $listQuestion);
        $comparisonTotalsQuestion = null;
        $comparisonSeriesQuestion = null;
        if ($comparableWindow !== null) {
            $comparisonTotalsQuestion = $this->buildQuestion($comparableWindow, [], $subjectFilters, $aggregationType, 1);
            $comparisonSeriesQuestion = $this->buildQuestion($comparableWindow, ['date'], $subjectFilters, $aggregationType, $comparableWindow->days);
            $searchQuestions->add($comparisonTotalsQuestion, $comparisonSeriesQuestion);
        }
        $this->assertOperationBudget($operation, $searchQuestions);
        $searchReports = $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $searchQuestions);
        $searchDetailParts = new SearchDetailParts();
        $searchDetailParts->totals = $this->buildTotals(
            $this->findReport($searchReports, $totalsQuestion),
            $comparisonTotalsQuestion ? $this->findReport($searchReports, $comparisonTotalsQuestion) : null,
            $searchWindow,
            $comparableWindow
        );
        $searchDetailParts->series = $this->buildSeries(
            $this->findReport($searchReports, $seriesQuestion),
            $comparisonSeriesQuestion ? $this->findReport($searchReports, $comparisonSeriesQuestion) : null,
            $searchWindow,
            $searchComparisonWindow
        );
        $searchDetailParts->rows = $this->attachWindows($this->findReport($searchReports, $listQuestion)->performanceRows, $searchWindow, null);
        return $searchDetailParts;
    }

    /**
     * Pages of `budgets.rowsPerCall` from row 0 up to `budgets.sortFetchCeiling`, one call per round; rows already
     * seen are dropped and counted; then a stable sort (ties: clicks descending, then the dimension value) and the
     * slice from `offset`. A failed page fails the list: a sort over part of the rows would mislead.
     *
     * @param InsightsSite $insightsSite
     * @param SearchWindow $searchWindow
     * @param SearchListRequest $searchListRequest
     * @param SearchQuestionFilters $searchQuestionFilters
     * @param string $aggregationType
     * @return SearchPerformanceRows
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function fetchAndSortRows(
        InsightsSite $insightsSite,
        SearchWindow $searchWindow,
        SearchListRequest $searchListRequest,
        SearchQuestionFilters $searchQuestionFilters,
        string $aggregationType
    ): SearchPerformanceRows {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $rowsPerCall = max(1, min($budgetsConfig->rowsPerCall, GoogleSearchConsoleSourceService::PROVIDER_MAX_ROW_LIMIT));
        $fetchCeiling = max($rowsPerCall, $budgetsConfig->sortFetchCeiling);
        $dimension = $searchListRequest->dimension;
        // method-local: the rows fetched so far by identity
        $fetchedRowsByIdentity = [];
        $duplicateRowsDropped = 0;
        $providerCalls = 0;
        $allFromCache = true;
        $oldestFetchedAt = null;
        $limitReached = false;
        $providerRowsExhausted = false;
        $firstPageRows = null;
        for ($startRow = 0; $startRow < $fetchCeiling; $startRow += $rowsPerCall) {
            $rowLimit = min($rowsPerCall, $fetchCeiling - $startRow);
            $pageQuestion = $this->buildQuestion($searchWindow, [$dimension], $searchQuestionFilters, $aggregationType, $rowLimit, $startRow);
            $pageRows = $this->findReport(
                $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $this->createQuestionSet($pageQuestion)),
                $pageQuestion
            )->performanceRows;
            $firstPageRows ??= $pageRows;
            $providerCalls += $pageRows->coverage->providerCalls;
            if (isset($pageRows->error)) {
                $pageRows->coverage->providerCalls = $providerCalls;
                $pageRows->coverage->sortScope = SearchRowsCoverage::SORT_SCOPE_FETCHED_ROWS;
                return $pageRows;
            }
            $allFromCache = $allFromCache && $pageRows->coverage->fromCache;
            if (isset($pageRows->coverage->fetchedAt)
                && ($oldestFetchedAt === null || $pageRows->coverage->fetchedAt->getTimestamp() < $oldestFetchedAt->getTimestamp())
            ) {
                $oldestFetchedAt = $pageRows->coverage->fetchedAt;
            }
            foreach ($pageRows->getElements() as $searchPerformanceRow) {
                $rowIdentity = $searchPerformanceRow->uniqueKey();
                if (isset($fetchedRowsByIdentity[$rowIdentity])) {
                    $duplicateRowsDropped++;
                    continue;
                }
                $fetchedRowsByIdentity[$rowIdentity] = $searchPerformanceRow;
            }
            if (count($pageRows->getElements()) < $rowLimit) {
                $providerRowsExhausted = true;
                break;
            }
            if ($startRow + $rowsPerCall >= $fetchCeiling) {
                $limitReached = true;
            }
        }
        $sortedRows = array_values($fetchedRowsByIdentity);
        $sortBy = $searchListRequest->sortBy;
        $isAscending = $searchListRequest->getEffectiveSortDirection() === SearchListRequest::SORT_DIRECTION_ASC;
        usort($sortedRows, static function (SearchPerformanceRow $firstRow, SearchPerformanceRow $secondRow) use ($sortBy, $isAscending, $dimension): int {
            $firstValue = self::readSortValue($firstRow, $sortBy);
            $secondValue = self::readSortValue($secondRow, $sortBy);
            if ($firstValue === null || $secondValue === null) {
                // rows without the value (no position) go last in either direction
                $comparison = ($firstValue === null) <=> ($secondValue === null);
            } else {
                $comparison = $isAscending ? $firstValue <=> $secondValue : $secondValue <=> $firstValue;
            }
            return $comparison
                ?: ($secondRow->metrics->clicks <=> $firstRow->metrics->clicks)
                ?: strcmp((string)$firstRow->getDimensionValue($dimension), (string)$secondRow->getDimensionValue($dimension));
        });

        $searchPerformanceRows = new SearchPerformanceRows();
        $searchPerformanceRows->provider = $firstPageRows->provider;
        $searchPerformanceRows->dimensions = $firstPageRows->dimensions;
        foreach (array_slice($sortedRows, $searchListRequest->offset, $searchListRequest->limit) as $searchPerformanceRow) {
            $searchPerformanceRows->add($searchPerformanceRow);
        }
        $searchRowsCoverage = clone $firstPageRows->coverage;
        $searchRowsCoverage->fetchedRows = count($sortedRows);
        $searchRowsCoverage->providerCalls = $providerCalls;
        $searchRowsCoverage->fromCache = $allFromCache;
        if ($oldestFetchedAt !== null) {
            $searchRowsCoverage->fetchedAt = $oldestFetchedAt;
        }
        $searchRowsCoverage->rowLimitPerCall = $rowsPerCall;
        $searchRowsCoverage->limitReached = $limitReached;
        $searchRowsCoverage->providerRowsExhausted = $providerRowsExhausted;
        $searchRowsCoverage->sortScope = SearchRowsCoverage::SORT_SCOPE_FETCHED_ROWS;
        $searchRowsCoverage->duplicateRowsDropped = $duplicateRowsDropped;
        $searchPerformanceRows->coverage = $searchRowsCoverage;
        return $searchPerformanceRows;
    }

    /**
     * The counterpart of every row in the comparison window (P8). Query and page lists ask exactly their keys
     * (`includingRegex ^(?:k1|…)$` in chunks of at most `counterpart.keysPerChunk` keys and
     * `counterpart.regexMaxLength` characters; a single longer key is asked with `equals`), at most
     * `counterpart.maxChunks` chunks in one batch; rows beyond them are `notRequested`. Countries and devices are small:
     * one call for the whole comparison window. A key without a row is `notObserved`; a failed chunk `failed`.
     *
     * @param InsightsSite $insightsSite
     * @param SearchPerformanceRows $searchPerformanceRows
     * @param string $dimension
     * @param SearchQuestionFilters $searchQuestionFilters the list's filters
     * @param string $aggregationType
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow $comparableWindow an available comparison window
     * @return void
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function addCounterparts(
        InsightsSite $insightsSite,
        SearchPerformanceRows $searchPerformanceRows,
        string $dimension,
        SearchQuestionFilters $searchQuestionFilters,
        string $aggregationType,
        SearchWindow $searchWindow,
        SearchComparisonWindow $comparableWindow
    ): void {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $searchRows = array_values($searchPerformanceRows->getElements());
        if (!$searchRows) {
            return;
        }
        // method-local: keys in row order, the chunk (question) of each key, the questions
        $rowKeys = [];
        foreach ($searchRows as $searchPerformanceRow) {
            $rowKey = $searchPerformanceRow->getDimensionValue($dimension);
            if ($rowKey !== null) {
                $rowKeys[$rowKey] = true;
            }
        }
        $rowKeys = array_map('strval', array_keys($rowKeys));
        $questionKeyByRowKey = [];
        $chunkQuestions = [];
        $isSmallList = $this->isSmallListDimension($dimension);
        if ($isSmallList) {
            $chunkQuestion = $this->buildQuestion($comparableWindow, [$dimension], $searchQuestionFilters, $aggregationType, self::SMALL_LIST_COUNTERPART_ROW_LIMIT);
            $chunkQuestions[$chunkQuestion->uniqueKey()] = $chunkQuestion;
            foreach ($rowKeys as $rowKey) {
                $questionKeyByRowKey[$rowKey] = $chunkQuestion->uniqueKey();
            }
        } else {
            $chunkKeys = [];
            $flushChunk = function () use (&$chunkKeys, &$chunkQuestions, &$questionKeyByRowKey, $comparableWindow, $dimension, $searchQuestionFilters, $aggregationType): void {
                if (!$chunkKeys) {
                    return;
                }
                $chunkFilter = count($chunkKeys) === 1 && strlen(SearchRegex::buildExactAlternation($chunkKeys)) > $this->getSiteInsightsConfigService()->getBudgetsConfig()->counterpartRegexMaxLength
                    ? SearchQuestionFilter::create($dimension, 'equals', $chunkKeys[0])
                    : SearchQuestionFilter::create($dimension, 'includingRegex', SearchRegex::buildExactAlternation($chunkKeys));
                $chunkFilters = $this->createFilterSet(...array_values($searchQuestionFilters->getElements()));
                $chunkFilters->add($chunkFilter);
                $chunkQuestion = $this->buildQuestion($comparableWindow, [$dimension], $chunkFilters, $aggregationType, count($chunkKeys));
                $chunkQuestions[$chunkQuestion->uniqueKey()] = $chunkQuestion;
                foreach ($chunkKeys as $chunkKey) {
                    $questionKeyByRowKey[$chunkKey] = $chunkQuestion->uniqueKey();
                }
                $chunkKeys = [];
            };
            foreach ($rowKeys as $rowKey) {
                if (count($chunkQuestions) >= $budgetsConfig->counterpartMaxChunks) {
                    break;
                }
                $candidateKeys = [...$chunkKeys, $rowKey];
                if ($chunkKeys
                    && (count($candidateKeys) > $budgetsConfig->counterpartKeysPerChunk
                        || strlen(SearchRegex::buildExactAlternation($candidateKeys)) > $budgetsConfig->counterpartRegexMaxLength)
                ) {
                    $flushChunk();
                    if (count($chunkQuestions) >= $budgetsConfig->counterpartMaxChunks) {
                        break;
                    }
                }
                $chunkKeys[] = $rowKey;
                if (strlen(SearchRegex::buildExactAlternation($chunkKeys)) > $budgetsConfig->counterpartRegexMaxLength) {
                    // a single key longer than the limit: its own equals call
                    $flushChunk();
                }
            }
            $flushChunk();
        }
        $searchQuestions = $this->createQuestionSet(...array_values($chunkQuestions));
        $searchReports = $this->getGoogleSearchConsoleSourceService()->loadReports($insightsSite, $searchQuestions);

        // method-local: the counterpart rows of each chunk by key
        $counterpartMetricsByQuestionKey = [];
        $chunkReportsByQuestionKey = [];
        foreach ($chunkQuestions as $questionKey => $chunkQuestion) {
            $chunkReport = $this->findReport($searchReports, $chunkQuestion);
            $chunkReportsByQuestionKey[$questionKey] = $chunkReport;
            $counterpartMetricsByQuestionKey[$questionKey] = [];
            foreach ($chunkReport->performanceRows->getElements() as $counterpartRow) {
                $counterpartKey = $counterpartRow->getDimensionValue($dimension);
                if ($counterpartKey !== null) {
                    $counterpartMetricsByQuestionKey[$questionKey][$counterpartKey] = $counterpartRow->metrics;
                }
            }
        }
        $counterpartsNotRequested = 0;
        foreach ($searchRows as $searchPerformanceRow) {
            $rowKey = $searchPerformanceRow->getDimensionValue($dimension);
            $questionKey = $rowKey !== null ? ($questionKeyByRowKey[$rowKey] ?? null) : null;
            if ($questionKey === null) {
                $counterpartsNotRequested++;
                $searchPerformanceRow->comparison = SearchMetricsComparison::createWithoutCounterpart($searchPerformanceRow->metrics, SearchMetricsComparison::COUNTERPART_NOT_REQUESTED);
                continue;
            }
            $chunkRows = $chunkReportsByQuestionKey[$questionKey]->performanceRows;
            if (isset($chunkRows->error)) {
                $searchPerformanceRow->comparison = SearchMetricsComparison::createWithoutCounterpart($searchPerformanceRow->metrics, SearchMetricsComparison::COUNTERPART_FAILED, $chunkRows->error);
                continue;
            }
            $counterpartMetrics = $counterpartMetricsByQuestionKey[$questionKey][$rowKey] ?? null;
            if ($counterpartMetrics === null && $isSmallList && !$chunkRows->coverage->providerRowsExhausted) {
                // the one comparison call was full: a key missing from it may lie beyond its rows
                $counterpartsNotRequested++;
                $searchPerformanceRow->comparison = SearchMetricsComparison::createWithoutCounterpart($searchPerformanceRow->metrics, SearchMetricsComparison::COUNTERPART_NOT_REQUESTED);
                continue;
            }
            $searchPerformanceRow->comparison = SearchMetricsComparison::createFromMetrics(
                $searchPerformanceRow->metrics,
                $counterpartMetrics,
                $searchWindow->days,
                $comparableWindow->days,
                $comparableWindow->comparisonIsPartial
            );
        }
        $searchPerformanceRows->coverage->counterpartChunks = count($chunkQuestions);
        $searchPerformanceRows->coverage->counterpartsNotRequested = $counterpartsNotRequested;
        $searchPerformanceRows->coverage->providerCalls += $this->sumProviderCalls($searchReports);
    }

    /**
     * @param SearchReport $currentReport
     * @param SearchReport|null $previousReport null: no comparison asked or available
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow|null $comparableWindow an available comparison window
     * @return SearchTotals the aggregate (its own question, never a sum of rows) with comparison
     */
    protected function buildTotals(SearchReport $currentReport, ?SearchReport $previousReport, SearchWindow $searchWindow, ?SearchComparisonWindow $comparableWindow): SearchTotals
    {
        $searchTotals = new SearchTotals();
        $currentRows = $currentReport->performanceRows;
        $searchTotals->coverage = $currentRows->coverage;
        if (isset($currentRows->error)) {
            $searchTotals->error = $currentRows->error;
            return $searchTotals;
        }
        $currentMetrics = $currentRows->first()?->metrics ?? SearchMetrics::createWithoutData($currentRows->coverage->responseAggregation);
        if ($previousReport === null || $comparableWindow === null) {
            $searchTotals->metrics = SearchMetricsComparison::createWithoutCounterpart($currentMetrics, SearchMetricsComparison::COUNTERPART_NOT_REQUESTED);
            return $searchTotals;
        }
        $previousRows = $previousReport->performanceRows;
        $searchTotals->comparisonCoverage = $previousRows->coverage;
        if (isset($previousRows->error)) {
            $searchTotals->metrics = SearchMetricsComparison::createWithoutCounterpart($currentMetrics, SearchMetricsComparison::COUNTERPART_FAILED, $previousRows->error);
            return $searchTotals;
        }
        $searchTotals->metrics = SearchMetricsComparison::createFromMetrics(
            $currentMetrics,
            $previousRows->first()?->metrics,
            $searchWindow->days,
            $comparableWindow->days,
            $comparableWindow->comparisonIsPartial
        );
        return $searchTotals;
    }

    /**
     * Every day of the window exactly once (a day the provider reported nothing for gets zeros, `hasData` false and
     * `isFilledDay` true), each compared with the day at the same offset of the comparison window.
     *
     * @param SearchReport $currentReport
     * @param SearchReport|null $previousReport
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow|null $searchComparisonWindow
     * @return SearchPerformanceRows
     */
    protected function buildSeries(SearchReport $currentReport, ?SearchReport $previousReport, SearchWindow $searchWindow, ?SearchComparisonWindow $searchComparisonWindow): SearchPerformanceRows
    {
        $currentRows = $currentReport->performanceRows;
        $seriesRows = new SearchPerformanceRows();
        $seriesRows->provider = $currentRows->provider;
        $seriesRows->dimensions = $currentRows->dimensions;
        $seriesRows->coverage = $currentRows->coverage;
        $this->attachWindows($seriesRows, $searchWindow, $searchComparisonWindow);
        if (isset($currentRows->error)) {
            $seriesRows->error = $currentRows->error;
            return $seriesRows;
        }
        $comparableWindow = $this->findComparableWindow($searchComparisonWindow);
        // method-local: rows by day (`Y-m-d`)
        $currentRowsByDay = [];
        foreach ($currentRows->getElements() as $currentRow) {
            $currentRowsByDay[(string)$currentRow->getDimensionValue('date')] = $currentRow;
        }
        $previousRowsByDay = [];
        if ($previousReport !== null && !isset($previousReport->performanceRows->error)) {
            foreach ($previousReport->performanceRows->getElements() as $previousRow) {
                $previousRowsByDay[(string)$previousRow->getDimensionValue('date')] = $previousRow;
            }
        }
        $aggregation = $currentRows->coverage->responseAggregation;
        $fromDay = SearchDays::fromDate($searchWindow->from);
        for ($dayIndex = 0; $dayIndex < $searchWindow->days; $dayIndex++) {
            $day = SearchDays::addDays($fromDay, $dayIndex);
            $seriesRow = $currentRowsByDay[$day] ?? null;
            if ($seriesRow === null) {
                $seriesRow = new SearchPerformanceRow();
                $seriesRow->date = SearchDays::toDate($day);
                $seriesRow->metrics = SearchMetrics::createWithoutData($aggregation);
                $seriesRow->isFilledDay = true;
            } else {
                $seriesRow->isFilledDay = false;
            }
            if ($comparableWindow !== null && $previousReport !== null) {
                $previousDay = SearchDays::addDays(SearchDays::fromDate($comparableWindow->from), $dayIndex);
                $seriesRow->comparison = $this->compareSeriesDay($seriesRow, $dayIndex, $comparableWindow, $previousReport, $previousRowsByDay[$previousDay] ?? null);
            }
            $seriesRows->add($seriesRow);
        }
        return $seriesRows;
    }

    /**
     * @param SearchPerformanceRow $seriesRow
     * @param int $dayIndex
     * @param SearchComparisonWindow $comparableWindow
     * @param SearchReport $previousReport
     * @param SearchPerformanceRow|null $previousRow the comparison window's row of the day at the same offset
     * @return SearchMetricsComparison the day compared with the day at the same offset (notRequested beyond a shorter
     *         comparison window)
     */
    protected function compareSeriesDay(SearchPerformanceRow $seriesRow, int $dayIndex, SearchComparisonWindow $comparableWindow, SearchReport $previousReport, ?SearchPerformanceRow $previousRow): SearchMetricsComparison
    {
        if ($dayIndex >= $comparableWindow->days) {
            return SearchMetricsComparison::createWithoutCounterpart($seriesRow->metrics, SearchMetricsComparison::COUNTERPART_NOT_REQUESTED);
        }
        $previousDay = SearchDays::addDays(SearchDays::fromDate($comparableWindow->from), $dayIndex);
        if (isset($previousReport->performanceRows->error)) {
            $searchMetricsComparison = SearchMetricsComparison::createWithoutCounterpart($seriesRow->metrics, SearchMetricsComparison::COUNTERPART_FAILED, $previousReport->performanceRows->error);
        } else {
            $searchMetricsComparison = SearchMetricsComparison::createFromMetrics($seriesRow->metrics, $previousRow?->metrics, 1, 1, false);
        }
        $searchMetricsComparison->previousDate = SearchDays::toDate($previousDay);
        return $searchMetricsComparison;
    }

    /**
     * @param SearchWindow $searchWindow the window (or an available comparison window)
     * @param string[] $dimensions
     * @param SearchQuestionFilters $searchQuestionFilters
     * @param string $aggregationType
     * @param int $rowLimit
     * @param int $startRow
     * @return SearchQuestion the question of that window with its own dates (built new, never shared)
     */
    protected function buildQuestion(SearchWindow $searchWindow, array $dimensions, SearchQuestionFilters $searchQuestionFilters, string $aggregationType, int $rowLimit, int $startRow = 0): SearchQuestion
    {
        $searchQuestion = new SearchQuestion();
        $searchQuestion->startDate = SearchDays::toDate(SearchDays::fromDate($searchWindow->from));
        $searchQuestion->endDate = SearchDays::toDate(SearchDays::fromDate($searchWindow->to));
        $searchQuestion->dimensions = $dimensions;
        $searchQuestion->filters = $this->createFilterSet(...array_values($searchQuestionFilters->getElements()));
        $searchQuestion->searchType = $searchWindow->searchType;
        $searchQuestion->dataState = $searchWindow->dataState;
        $searchQuestion->aggregationType = $aggregationType;
        $searchQuestion->rowLimit = max(1, min($rowLimit, GoogleSearchConsoleSourceService::PROVIDER_MAX_ROW_LIMIT));
        $searchQuestion->startRow = $startRow;
        if (isset($searchWindow->dataThroughDate)) {
            $searchQuestion->dataThroughDate = SearchDays::toDate(SearchDays::fromDate($searchWindow->dataThroughDate));
        }
        return $searchQuestion;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param SearchListRequest $searchListRequest a validated request
     * @return SearchQuestionFilters the request's filters as provider filters
     * @throws BadRequestException for an unknown page group
     */
    protected function buildListFilters(InsightsSite $insightsSite, SearchListRequest $searchListRequest): SearchQuestionFilters
    {
        $searchQuestionFilters = new SearchQuestionFilters();
        $filterValues = [
            ['query', 'contains', $searchListRequest->queryContains ?? null],
            ['query', 'equals', $searchListRequest->queryEquals ?? null],
            ['page', 'contains', $searchListRequest->pageContains ?? null],
            ['page', 'equals', $searchListRequest->pageEquals ?? null],
            ['country', 'equals', isset($searchListRequest->country) ? strtolower($searchListRequest->country) : null],
            ['device', 'equals', isset($searchListRequest->device) ? strtoupper($searchListRequest->device) : null],
        ];
        foreach ($filterValues as [$dimension, $operator, $expression]) {
            if ($expression !== null) {
                $searchQuestionFilter = SearchQuestionFilter::create($dimension, $operator, $expression);
                $searchQuestionFilters->add($searchQuestionFilter);
            }
        }
        if (isset($searchListRequest->pageGroup)) {
            $insightsPageGroup = $insightsSite->pageGroups->findPageGroupByKey($searchListRequest->pageGroup);
            if ($insightsPageGroup === null) {
                throw new BadRequestException('The site has no page group "' . mb_substr($searchListRequest->pageGroup, 0, 64) . '"');
            }
            $pageGroupFilter = SearchQuestionFilter::create('page', 'includingRegex', SearchRegex::buildPageGroupExpression($insightsSite, $insightsPageGroup));
            $searchQuestionFilters->add($pageGroupFilter);
        }
        return $searchQuestionFilters;
    }

    /**
     * @param SearchListRequest $searchListRequest
     * @return void
     * @throws BadRequestException naming the first invalid value (DTO attributes may be schema-only)
     */
    protected function validateListRequest(SearchListRequest $searchListRequest): void
    {
        if (!in_array($searchListRequest->dimension, SearchListRequest::LIST_DIMENSIONS, true)) {
            throw new BadRequestException('dimension must be one of ' . implode(', ', SearchListRequest::LIST_DIMENSIONS));
        }
        if ($searchListRequest->limit < 1 || $searchListRequest->limit > SearchListRequest::MAX_LIMIT) {
            throw new BadRequestException('limit must be 1 to ' . SearchListRequest::MAX_LIMIT);
        }
        if ($searchListRequest->offset < 0 || $searchListRequest->offset > SearchListRequest::MAX_OFFSET) {
            throw new BadRequestException('offset must be 0 to ' . SearchListRequest::MAX_OFFSET);
        }
        if (!in_array($searchListRequest->sortBy, SearchListRequest::SORT_BY_VALUES, true)) {
            throw new BadRequestException('sortBy must be one of ' . implode(', ', SearchListRequest::SORT_BY_VALUES));
        }
        if (isset($searchListRequest->sortDirection) && !in_array($searchListRequest->sortDirection, [SearchListRequest::SORT_DIRECTION_ASC, SearchListRequest::SORT_DIRECTION_DESC], true)) {
            throw new BadRequestException('sortDirection must be asc or desc');
        }
        foreach (['queryContains' => self::QUERY_MAX_LENGTH, 'queryEquals' => self::QUERY_MAX_LENGTH, 'pageContains' => self::URL_MAX_LENGTH, 'pageEquals' => self::URL_MAX_LENGTH] as $filterName => $maxLength) {
            $filterValue = $searchListRequest->$filterName ?? null;
            if ($filterValue !== null && ($filterValue === '' || mb_strlen($filterValue) > $maxLength)) {
                throw new BadRequestException($filterName . ' must have 1 to ' . $maxLength . ' characters');
            }
        }
        if (isset($searchListRequest->pageGroup) && preg_match(self::PAGE_GROUP_KEY_PATTERN, $searchListRequest->pageGroup) !== 1) {
            throw new BadRequestException('pageGroup must be the key of a configured page group');
        }
        if (isset($searchListRequest->country) && preg_match(self::COUNTRY_PATTERN, $searchListRequest->country) !== 1) {
            throw new BadRequestException('country must be three lower-case letters (ISO 3166-1 alpha-3)');
        }
        if (isset($searchListRequest->device) && !in_array($searchListRequest->device, SearchListRequest::DEVICES, true)) {
            throw new BadRequestException('device must be one of ' . implode(', ', SearchListRequest::DEVICES));
        }
    }

    /**
     * @param string[] $dimensions
     * @return string[] the dimensions, distinct, in the order asked
     * @throws BadRequestException
     */
    protected function validateExportDimensions(array $dimensions): array
    {
        $distinctDimensions = array_values(array_unique($dimensions));
        if (!$distinctDimensions) {
            throw new BadRequestException('An export needs at least one dimension');
        }
        foreach ($distinctDimensions as $dimension) {
            if (!in_array($dimension, SearchExportRequest::EXPORT_DIMENSIONS, true)) {
                throw new BadRequestException('Export dimensions must be of ' . implode(', ', SearchExportRequest::EXPORT_DIMENSIONS));
            }
        }
        return $distinctDimensions;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $url
     * @return string the URL trimmed, when it belongs to the property (URL prefix: starts with it; domain: its host
     *         is the domain or a subdomain of it)
     * @throws BadRequestException
     */
    protected function normalizePageUrl(InsightsSite $insightsSite, string $url): string
    {
        $pageUrl = trim($url);
        if ($pageUrl === '' || strlen($pageUrl) > self::URL_MAX_LENGTH || preg_match('#^https?://#i', $pageUrl) !== 1) {
            throw new BadRequestException('url must be an http(s) URL of at most ' . self::URL_MAX_LENGTH . ' characters');
        }
        if ($insightsSite->isDomainProperty()) {
            $urlHost = strtolower((string)parse_url($pageUrl, PHP_URL_HOST));
            $domain = strtolower($insightsSite->getPropertyHost());
            if ($urlHost !== $domain && !str_ends_with($urlHost, '.' . $domain)) {
                throw new BadRequestException('url does not belong to the property of site "' . $insightsSite->key . '"');
            }
        } elseif (!str_starts_with($pageUrl, $insightsSite->property)) {
            throw new BadRequestException('url must start with the property of site "' . $insightsSite->key . '"');
        }
        return $pageUrl;
    }

    /**
     * @param string $dimension
     * @param SearchQuestionFilters $searchQuestionFilters
     * @return string auto when grouped or filtered by page or query (Google forbids byProperty with a page filter);
     *         byProperty for countries and devices
     */
    protected function determineListAggregation(string $dimension, SearchQuestionFilters $searchQuestionFilters): string
    {
        foreach ($searchQuestionFilters->getElements() as $searchQuestionFilter) {
            if ($searchQuestionFilter->dimension === 'page') {
                return SearchQuestion::AGGREGATION_AUTO;
            }
        }
        return $this->isSmallListDimension($dimension) ? SearchQuestion::AGGREGATION_BY_PROPERTY : SearchQuestion::AGGREGATION_AUTO;
    }

    /** @return bool countries and devices: few rows, compared with one call */
    protected function isSmallListDimension(string $dimension): bool
    {
        return in_array($dimension, [SearchListRequest::DIMENSION_COUNTRY, SearchListRequest::DIMENSION_DEVICE], true);
    }

    /**
     * @param SearchPerformanceRows $searchPerformanceRows
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow|null $searchComparisonWindow
     * @return SearchPerformanceRows the rows with their windows set
     */
    protected function attachWindows(SearchPerformanceRows $searchPerformanceRows, SearchWindow $searchWindow, ?SearchComparisonWindow $searchComparisonWindow): SearchPerformanceRows
    {
        $searchPerformanceRows->window = $searchWindow;
        if ($searchComparisonWindow !== null) {
            $searchPerformanceRows->comparisonWindow = $searchComparisonWindow;
        }
        return $searchPerformanceRows;
    }

    /**
     * @param SearchComparisonWindow|null $searchComparisonWindow
     * @return SearchComparisonWindow|null the window when a comparison can be asked, else null (none, or refused)
     */
    protected function findComparableWindow(?SearchComparisonWindow $searchComparisonWindow): ?SearchComparisonWindow
    {
        return $searchComparisonWindow !== null && $searchComparisonWindow->isAvailable() ? $searchComparisonWindow : null;
    }

    /**
     * @param SearchReports $searchReports
     * @param SearchQuestion $searchQuestion
     * @return SearchReport
     * @throws InternalErrorException when the source returned no answer for a question it was asked
     */
    protected function findReport(SearchReports $searchReports, SearchQuestion $searchQuestion): SearchReport
    {
        $searchReport = $searchReports->findReportForQuestion($searchQuestion);
        if ($searchReport === null) {
            throw new InternalErrorException('The search source returned no answer for a question it was asked');
        }
        return $searchReport;
    }

    /** @return int provider calls of every report (cache hits count 0) */
    protected function sumProviderCalls(SearchReports $searchReports): int
    {
        $providerCalls = 0;
        foreach ($searchReports->getElements() as $searchReport) {
            $providerCalls += $searchReport->performanceRows->coverage->providerCalls;
        }
        return $providerCalls;
    }

    /**
     * @param string $operation
     * @param SearchQuestions $searchQuestions
     * @return void the questions plus the availability probe must fit the operation's budget
     * @throws SiteInsightsBudgetExceededException
     * @throws BadRequestException
     */
    protected function assertOperationBudget(string $operation, SearchQuestions $searchQuestions): void
    {
        $this->assertPlannedCalls($operation, count($searchQuestions->getElements()) + 1);
    }

    /**
     * @param string $operation
     * @param int $plannedCalls
     * @return void
     * @throws SiteInsightsBudgetExceededException
     * @throws BadRequestException
     */
    protected function assertPlannedCalls(string $operation, int $plannedCalls): void
    {
        /** @var SiteInsightsProviderBudgetsService $siteInsightsProviderBudgetsService */
        $siteInsightsProviderBudgetsService = DDDService::instance()->getService(SiteInsightsProviderBudgetsService::class);
        $siteInsightsProviderBudgetsService->assertOperationBudget($operation, $plannedCalls);
    }

    protected function createQuestionSet(SearchQuestion ...$searchQuestions): SearchQuestions
    {
        $searchQuestionSet = new SearchQuestions();
        foreach ($searchQuestions as $searchQuestion) {
            $searchQuestionSet->add($searchQuestion);
        }
        return $searchQuestionSet;
    }

    protected function createFilterSet(SearchQuestionFilter ...$searchQuestionFilters): SearchQuestionFilters
    {
        $searchQuestionFilterSet = new SearchQuestionFilters();
        foreach ($searchQuestionFilters as $searchQuestionFilter) {
            $searchQuestionFilterSet->add($searchQuestionFilter);
        }
        return $searchQuestionFilterSet;
    }

    /**
     * @param SearchPerformanceRow $searchPerformanceRow
     * @param string $sortBy
     * @return float|null the value the list is sorted by; null when the row has no position
     */
    protected static function readSortValue(SearchPerformanceRow $searchPerformanceRow, string $sortBy): ?float
    {
        return match ($sortBy) {
            SearchListRequest::SORT_BY_IMPRESSIONS => (float)$searchPerformanceRow->metrics->impressions,
            SearchListRequest::SORT_BY_CTR => $searchPerformanceRow->metrics->ctr,
            SearchListRequest::SORT_BY_POSITION => $searchPerformanceRow->metrics->position ?? null,
            default => (float)$searchPerformanceRow->metrics->clicks,
        };
    }

    /**
     * @param string $siteKey
     * @return InsightsSite the site, after the access policy let the account view
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws InternalErrorException
     */
    protected function requireViewerAndFindSite(string $siteKey): InsightsSite
    {
        $this->getSiteInsightsAccessPolicyService()->requireViewer();
        /** @var InsightsSitesService $insightsSitesService */
        $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
        return $insightsSitesService->getSite($siteKey);
    }

    /**
     * @param InsightsSite $insightsSite
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchWindow
     * @throws BadRequestException
     */
    protected function buildWindow(InsightsSite $insightsSite, SearchWindowRequest $searchWindowRequest): SearchWindow
    {
        /** @var SearchWindowsService $searchWindowsService */
        $searchWindowsService = DDDService::instance()->getService(SearchWindowsService::class);
        return $searchWindowsService->buildWindow($insightsSite, $searchWindowRequest);
    }

    /**
     * @param SearchWindow $searchWindow
     * @param string $compare
     * @return SearchComparisonWindow|null
     * @throws BadRequestException
     */
    protected function buildComparisonWindow(SearchWindow $searchWindow, string $compare): ?SearchComparisonWindow
    {
        /** @var SearchWindowsService $searchWindowsService */
        $searchWindowsService = DDDService::instance()->getService(SearchWindowsService::class);
        return $searchWindowsService->buildComparisonWindow($searchWindow, $compare);
    }

    protected function getGoogleSearchConsoleSourceService(): GoogleSearchConsoleSourceService
    {
        return DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);
    }

    protected function getSiteInsightsConfigService(): SiteInsightsConfigService
    {
        return DDDService::instance()->getService(SiteInsightsConfigService::class);
    }

    protected function getSiteInsightsAccessPolicyService(): SiteInsightsAccessPolicyService
    {
        return DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
    }
}
