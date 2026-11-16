<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsBudgetsConfig;
use DDD\Domain\SiteInsights\Entities\Config\SiteInsightsOpportunityRuleConfig;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchClickRateBaseline;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunities;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunitiesEvaluation;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunitiesRequest;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunity;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunityKindResult;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunityKindResults;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunityThresholds;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchPositionBucket;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchPositionBuckets;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Entities\Search\SearchPartError;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestion;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilter;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestionFilters;
use DDD\Domain\SiteInsights\Entities\Search\SearchQuestions;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Utils\SearchRegex;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;

/**
 * The opportunity rules: fixed rules over the rows of one window that return candidates with their evidence, never
 * diagnoses. Kinds, in the order they are evaluated:
 *
 * 1. `strikingDistanceQuery`: queries with an average position in [positionFrom, positionTo] and enough impressions;
 * 2. `weakClickRateForPosition`: query × device rows whose click rate is below `ratio` × the click rate of their
 *    position bucket on their device (or of all devices, when the device bucket is thin and the fallback is on);
 * 3. `severalPagesForQuery`: top queries for which at least `minPages` pages get a real share of the impressions;
 * 4. `risingQuery`, `decliningQuery`, `risingPage`, `decliningPage`: clicks that changed between the window and its
 *    comparison window, candidates taken from both periods and their counterparts asked by key.
 *
 * All kinds together may plan `budgets.callsPerOperation.opportunities` provider calls (the probe counts as one);
 * sources are shared between kinds and counted at the first kind that reads them. A kind that would exceed the budget
 * is returned with `error` budgetRefused, never left out. Thresholds in clicks or impressions are per 28 days and
 * scaled to the window; they come from `opportunities.php` and the site's own `opportunities` settings.
 */
class SearchOpportunitiesService extends SearchPerformanceService
{
    /** @var int[] Bounds of the position buckets of weakClickRateForPosition: bucket i is [b[i], b[i + 1]) on the rounded-down position; positions from 11 on are not judged */
    public const array POSITION_BUCKET_BOUNDS = [1, 2, 3, 4, 6, 11];

    /** @var string[] Devices of the query × device report */
    public const array DEVICES = ['desktop', 'mobile', 'tablet'];

    /** @var string[] What a result of each kind does not prove, by kind */
    public const array NOTES_BY_KIND = [
        SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY => 'An average position over the window; it does not say which page ranks or how hard the queries ahead are.',
        SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION => 'An average position does not prove an expected click rate: features on the result page, intent and brand change it.',
        SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY => 'Two pages shown for one query are not proven competition; the provider drops rows in query × page reports.',
        SearchOpportunity::KIND_RISING_QUERY => 'A change between two periods, not its cause; seasonality and anonymised queries are not taken into account.',
        SearchOpportunity::KIND_DECLINING_QUERY => 'A change between two periods, not its cause; seasonality and anonymised queries are not taken into account.',
        SearchOpportunity::KIND_RISING_PAGE => 'A change between two periods, not its cause; a page counts per page, higher than the property totals.',
        SearchOpportunity::KIND_DECLINING_PAGE => 'A change between two periods, not its cause; a page counts per page, higher than the property totals.',
    ];

    /**
     * @param string $siteKey
     * @param SearchOpportunitiesRequest $searchOpportunitiesRequest the window and, optionally, one kind
     * @return SearchOpportunities the candidates of every kind asked, with one kind result each
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException for an unknown kind or an invalid window
     * @throws InternalErrorException for invalid opportunity settings
     * @throws SiteInsightsProviderUnavailableException when no token can be obtained
     */
    public function getOpportunities(string $siteKey, SearchOpportunitiesRequest $searchOpportunitiesRequest): SearchOpportunities
    {
        $insightsSite = $this->requireViewerAndFindSite($siteKey);
        $kindsToEvaluate = SearchOpportunity::KINDS;
        if (isset($searchOpportunitiesRequest->kind)) {
            if (!in_array($searchOpportunitiesRequest->kind, SearchOpportunity::KINDS, true)) {
                throw new BadRequestException('kind must be one of ' . implode(', ', SearchOpportunity::KINDS));
            }
            $kindsToEvaluate = [$searchOpportunitiesRequest->kind];
        }
        $searchOpportunitiesEvaluation = new SearchOpportunitiesEvaluation();
        $searchOpportunitiesEvaluation->insightsSite = $insightsSite;
        $searchOpportunitiesEvaluation->opportunitiesConfig = $this->getSiteInsightsConfigService()->getOpportunitiesConfig($insightsSite->key);
        $searchOpportunitiesEvaluation->callsAllowed = (int)$this->getSiteInsightsConfigService()->getBudgetsConfig()->getCallsAllowedForOperation(SiteInsightsBudgetsConfig::OPERATION_OPPORTUNITIES);
        $searchOpportunitiesEvaluation->window = $this->buildWindow($insightsSite, $searchOpportunitiesRequest);
        if (array_intersect($kindsToEvaluate, $this->getComparisonKinds())) {
            $compare = $searchOpportunitiesRequest->compare === SearchWindowRequest::COMPARE_NONE
                ? SearchComparisonWindow::KIND_PREVIOUS_PERIOD
                : $searchOpportunitiesRequest->compare;
            $comparisonWindow = $this->buildComparisonWindow($searchOpportunitiesEvaluation->window, $compare);
            if ($comparisonWindow !== null) {
                $searchOpportunitiesEvaluation->comparisonWindow = $comparisonWindow;
            }
        }

        $searchOpportunities = new SearchOpportunities();
        $searchOpportunities->siteKey = $insightsSite->key;
        $searchOpportunities->window = $searchOpportunitiesEvaluation->window;
        if (isset($searchOpportunitiesEvaluation->comparisonWindow)) {
            $searchOpportunities->comparisonWindow = $searchOpportunitiesEvaluation->comparisonWindow;
        }
        $searchOpportunities->kinds = new SearchOpportunityKindResults();
        $searchOpportunities->callsAllowed = $searchOpportunitiesEvaluation->callsAllowed;
        foreach ($kindsToEvaluate as $kind) {
            $searchOpportunityKindResult = $this->evaluateKind($searchOpportunitiesEvaluation, $kind, $searchOpportunities);
            $searchOpportunities->kinds->add($searchOpportunityKindResult);
        }
        $searchOpportunities->providerCalls = $searchOpportunitiesEvaluation->providerCalls;
        return $searchOpportunities;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param string $kind
     * @param SearchOpportunities $searchOpportunities the results of the kind are added here
     * @return SearchOpportunityKindResult
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function evaluateKind(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, string $kind, SearchOpportunities $searchOpportunities): SearchOpportunityKindResult
    {
        $searchOpportunityKindResult = new SearchOpportunityKindResult();
        $searchOpportunityKindResult->kind = $kind;
        $searchOpportunityKindResult->note = self::NOTES_BY_KIND[$kind];
        $searchOpportunityKindResult->thresholds = $this->buildThresholds($searchOpportunitiesEvaluation, $kind);
        $windowDays = $searchOpportunitiesEvaluation->window->days;
        if ($windowDays < $searchOpportunitiesEvaluation->opportunitiesConfig->minWindowDays) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_WINDOW_TOO_SHORT;
            return $searchOpportunityKindResult;
        }
        $isComparisonKind = in_array($kind, $this->getComparisonKinds(), true);
        if ($isComparisonKind && $this->findComparableWindow($searchOpportunitiesEvaluation->comparisonWindow ?? null) === null) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_NO_COMPARISON;
            return $searchOpportunityKindResult;
        }
        $plannedCalls = $this->planCallsOfKind($searchOpportunitiesEvaluation, $kind);
        if ($searchOpportunitiesEvaluation->plannedCalls + $plannedCalls > $searchOpportunitiesEvaluation->callsAllowed) {
            $searchOpportunityKindResult->error = SearchPartError::create(
                SiteInsightsErrorCause::BUDGET_REFUSED,
                sprintf(
                    'The kind %s would need %d more provider calls; %d of %d are planned (budgets.callsPerOperation.opportunities)',
                    $kind,
                    $plannedCalls,
                    $searchOpportunitiesEvaluation->plannedCalls,
                    $searchOpportunitiesEvaluation->callsAllowed
                )
            );
            return $searchOpportunityKindResult;
        }
        $searchOpportunitiesEvaluation->plannedCalls += $plannedCalls;
        $providerCallsBefore = $searchOpportunitiesEvaluation->providerCalls;
        $kindOpportunities = match ($kind) {
            SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY => $this->findStrikingDistanceQueries($searchOpportunitiesEvaluation, $searchOpportunityKindResult),
            SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION => $this->findWeakClickRates($searchOpportunitiesEvaluation, $searchOpportunityKindResult),
            SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY => $this->findSeveralPagesForQueries($searchOpportunitiesEvaluation, $searchOpportunityKindResult),
            default => $this->findRisingAndDeclining($searchOpportunitiesEvaluation, $searchOpportunityKindResult),
        };
        $searchOpportunityKindResult->providerCalls = $searchOpportunitiesEvaluation->providerCalls - $providerCallsBefore;
        foreach ($kindOpportunities->getElements() as $searchOpportunity) {
            $searchOpportunities->add($searchOpportunity);
        }
        $searchOpportunityKindResult->resultCount = count($kindOpportunities->getElements());
        if ($searchOpportunityKindResult->resultCount === 0 && !isset($searchOpportunityKindResult->emptyReason)) {
            $searchOpportunityKindResult->emptyReason = isset($searchOpportunityKindResult->error)
                ? SearchOpportunityKindResult::EMPTY_SOURCE_FAILED
                : SearchOpportunityKindResult::EMPTY_BELOW_THRESHOLDS;
        }
        return $searchOpportunityKindResult;
    }

    /**
     * Rule 1: queries of the window (up to budgets.sortFetchCeiling, provider order) with positionFrom ≤ position ≤
     * positionTo and impressions ≥ minImpressions (scaled), brand queries left out; ordered by impressions.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchOpportunityKindResult $searchOpportunityKindResult
     * @return SearchOpportunities the results (no metadata)
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function findStrikingDistanceQueries(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchOpportunityKindResult $searchOpportunityKindResult): SearchOpportunities
    {
        $kindOpportunities = new SearchOpportunities();
        $queryRows = $this->loadQueryRowsOfWindow($searchOpportunitiesEvaluation);
        if (!$this->acceptSource($queryRows, $searchOpportunityKindResult)) {
            return $kindOpportunities;
        }
        $strikingDistanceConfig = $searchOpportunitiesEvaluation->opportunitiesConfig->strikingDistanceQuery;
        $minImpressions = SiteInsightsOpportunityRuleConfig::scaleToWindow($strikingDistanceConfig->minImpressions, $searchOpportunitiesEvaluation->window->days);
        $hitRows = [];
        foreach ($queryRows->getElements() as $queryRow) {
            $position = $queryRow->metrics->position ?? null;
            if ($position === null || $position < $strikingDistanceConfig->positionFrom || $position > $strikingDistanceConfig->positionTo) {
                continue;
            }
            if ($queryRow->metrics->impressions < $minImpressions) {
                continue;
            }
            if ($strikingDistanceConfig->excludeBrand && $this->isBrandQuery($searchOpportunitiesEvaluation->insightsSite, (string)($queryRow->query ?? ''))) {
                continue;
            }
            $hitRows[] = $queryRow;
        }
        usort($hitRows, static fn (SearchPerformanceRow $firstRow, SearchPerformanceRow $secondRow): int => [$secondRow->metrics->impressions, (string)($firstRow->query ?? '')] <=> [$firstRow->metrics->impressions, (string)($secondRow->query ?? '')]);
        foreach (array_slice($hitRows, 0, max(0, $strikingDistanceConfig->maxResults)) as $hitRow) {
            $searchOpportunity = $this->createOpportunity(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY, SearchOpportunity::SUBJECT_QUERY, $hitRow, $this->createRowSet(['query'], $hitRow));
            $kindOpportunities->add($searchOpportunity);
        }
        return $kindOpportunities;
    }

    /**
     * Rule 2: query × device rows with impressions ≥ minImpressions (scaled), brand queries left out, judged against
     * the click rate (Σ clicks ÷ Σ impressions) of their position bucket on their device; a device bucket below
     * minRowsPerBucket takes the bucket of all devices when fallbackToAllDevices is on. A hit: click rate < ratio ×
     * the baseline. Ordered by impressions. Every bucket is returned with its row count (`positionBuckets`).
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchOpportunityKindResult $searchOpportunityKindResult
     * @return SearchOpportunities
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function findWeakClickRates(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchOpportunityKindResult $searchOpportunityKindResult): SearchOpportunities
    {
        $kindOpportunities = new SearchOpportunities();
        $queryDeviceRows = $this->loadQueryDeviceRowsOfWindow($searchOpportunitiesEvaluation);
        if (!$this->acceptSource($queryDeviceRows, $searchOpportunityKindResult)) {
            return $kindOpportunities;
        }
        $weakClickRateConfig = $searchOpportunitiesEvaluation->opportunitiesConfig->weakClickRateForPosition;
        $minImpressions = SiteInsightsOpportunityRuleConfig::scaleToWindow($weakClickRateConfig->minImpressions, $searchOpportunitiesEvaluation->window->days);
        // method-local: the judged rows with their bucket index, and the sums per device and bucket
        $judgedRowsWithBucket = [];
        $clicksByDeviceAndBucket = [];
        $impressionsByDeviceAndBucket = [];
        $rowCountsByDeviceAndBucket = [];
        foreach ($queryDeviceRows->getElements() as $queryDeviceRow) {
            $bucketIndex = $this->findPositionBucketIndex($queryDeviceRow->metrics->position ?? null);
            if ($bucketIndex === null || $queryDeviceRow->metrics->impressions < $minImpressions) {
                continue;
            }
            if ($weakClickRateConfig->excludeBrand && $this->isBrandQuery($searchOpportunitiesEvaluation->insightsSite, (string)($queryDeviceRow->query ?? ''))) {
                continue;
            }
            $device = (string)($queryDeviceRow->device ?? '');
            foreach ([$device, SearchPositionBucket::DEVICE_ALL] as $bucketDevice) {
                $bucketKey = $bucketDevice . '|' . $bucketIndex;
                $clicksByDeviceAndBucket[$bucketKey] = ($clicksByDeviceAndBucket[$bucketKey] ?? 0) + $queryDeviceRow->metrics->clicks;
                $impressionsByDeviceAndBucket[$bucketKey] = ($impressionsByDeviceAndBucket[$bucketKey] ?? 0) + $queryDeviceRow->metrics->impressions;
                $rowCountsByDeviceAndBucket[$bucketKey] = ($rowCountsByDeviceAndBucket[$bucketKey] ?? 0) + 1;
            }
            $judgedRowsWithBucket[] = [$queryDeviceRow, $bucketIndex];
        }
        $searchPositionBuckets = new SearchPositionBuckets();
        $anyBucketHasBaseline = false;
        foreach ([...self::DEVICES, SearchPositionBucket::DEVICE_ALL] as $bucketDevice) {
            for ($bucketIndex = 0; $bucketIndex < count(self::POSITION_BUCKET_BOUNDS) - 1; $bucketIndex++) {
                $bucketKey = $bucketDevice . '|' . $bucketIndex;
                $searchPositionBucket = new SearchPositionBucket();
                $searchPositionBucket->device = $bucketDevice;
                $searchPositionBucket->positionFrom = (float)self::POSITION_BUCKET_BOUNDS[$bucketIndex];
                $searchPositionBucket->positionTo = (float)self::POSITION_BUCKET_BOUNDS[$bucketIndex + 1];
                $searchPositionBucket->rowCount = $rowCountsByDeviceAndBucket[$bucketKey] ?? 0;
                if (($impressionsByDeviceAndBucket[$bucketKey] ?? 0) > 0) {
                    $searchPositionBucket->clickRate = $clicksByDeviceAndBucket[$bucketKey] / $impressionsByDeviceAndBucket[$bucketKey];
                }
                $searchPositionBucket->hasBaseline = $searchPositionBucket->rowCount >= max(1, $weakClickRateConfig->minRowsPerBucket);
                if ($searchPositionBucket->hasBaseline && ($bucketDevice !== SearchPositionBucket::DEVICE_ALL || $weakClickRateConfig->fallbackToAllDevices)) {
                    $anyBucketHasBaseline = true;
                }
                $searchPositionBuckets->add($searchPositionBucket);
            }
        }
        $searchOpportunityKindResult->positionBuckets = $searchPositionBuckets;
        if (!$judgedRowsWithBucket) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_NO_ROWS;
            return $kindOpportunities;
        }
        if (!$anyBucketHasBaseline) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_THIN_BASELINE;
            return $kindOpportunities;
        }
        $hitRowsWithBaseline = [];
        foreach ($judgedRowsWithBucket as [$queryDeviceRow, $bucketIndex]) {
            $positionFrom = (float)self::POSITION_BUCKET_BOUNDS[$bucketIndex];
            $baselineBucket = $searchPositionBuckets->findBucket((string)($queryDeviceRow->device ?? ''), $positionFrom);
            $baselineScope = SearchClickRateBaseline::SCOPE_DEVICE;
            if ($baselineBucket === null || !$baselineBucket->hasBaseline) {
                if (!$weakClickRateConfig->fallbackToAllDevices) {
                    continue;
                }
                $baselineBucket = $searchPositionBuckets->findBucket(SearchPositionBucket::DEVICE_ALL, $positionFrom);
                $baselineScope = SearchClickRateBaseline::SCOPE_ALL_DEVICES;
                if ($baselineBucket === null || !$baselineBucket->hasBaseline) {
                    continue;
                }
            }
            $baselineClickRate = $baselineBucket->clickRate ?? 0.0;
            if ($baselineClickRate <= 0.0 || $queryDeviceRow->metrics->ctr >= $weakClickRateConfig->ratio * $baselineClickRate) {
                continue;
            }
            $searchClickRateBaseline = new SearchClickRateBaseline();
            $searchClickRateBaseline->positionFrom = $baselineBucket->positionFrom;
            $searchClickRateBaseline->positionTo = $baselineBucket->positionTo;
            $searchClickRateBaseline->baselineScope = $baselineScope;
            $searchClickRateBaseline->clickRate = $baselineClickRate;
            $searchClickRateBaseline->rowCount = $baselineBucket->rowCount;
            $searchClickRateBaseline->ratio = $weakClickRateConfig->ratio;
            $hitRowsWithBaseline[] = [$queryDeviceRow, $searchClickRateBaseline];
        }
        usort($hitRowsWithBaseline, static fn (array $firstHit, array $secondHit): int => $secondHit[0]->metrics->impressions <=> $firstHit[0]->metrics->impressions);
        foreach (array_slice($hitRowsWithBaseline, 0, max(0, $weakClickRateConfig->maxResults)) as [$hitRow, $searchClickRateBaseline]) {
            $searchOpportunity = $this->createOpportunity(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION, SearchOpportunity::SUBJECT_QUERY, $hitRow, $this->createRowSet(['query', 'device'], $hitRow));
            $searchOpportunity->device = (string)($hitRow->device ?? '');
            $searchOpportunity->baseline = $searchClickRateBaseline;
            $kindOpportunities->add($searchOpportunity);
        }
        return $kindOpportunities;
    }

    /**
     * Rule 3: the top `topQueries` queries (provider order) asked in one query × page report (filter by query in RE2
     * chunks, `pagesPerQueryMax` rows per query); a hit has at least `minPages` pages with impressions ≥
     * minImpressionsPerPage (scaled) and a share ≥ minShare of the query's impressions in the report.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchOpportunityKindResult $searchOpportunityKindResult
     * @return SearchOpportunities
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function findSeveralPagesForQueries(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchOpportunityKindResult $searchOpportunityKindResult): SearchOpportunities
    {
        $kindOpportunities = new SearchOpportunities();
        $queryRows = $this->loadQueryRowsOfWindow($searchOpportunitiesEvaluation);
        if (isset($queryRows->error)) {
            $searchOpportunityKindResult->error = $queryRows->error;
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_SOURCE_FAILED;
            return $kindOpportunities;
        }
        if (!count($queryRows->getElements())) {
            $searchOpportunityKindResult->sourceCoverage = $queryRows->coverage;
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_NO_ROWS;
            return $kindOpportunities;
        }
        $queryPageRows = $this->loadQueryPageRowsOfTopQueries($searchOpportunitiesEvaluation, $queryRows);
        $searchOpportunityKindResult->sourceCoverage = $queryPageRows->coverage;
        if (isset($queryPageRows->error)) {
            $searchOpportunityKindResult->error = $queryPageRows->error;
            $searchOpportunityKindResult->isPartial = count($queryPageRows->getElements()) > 0;
            if (!$searchOpportunityKindResult->isPartial) {
                $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_SOURCE_FAILED;
                return $kindOpportunities;
            }
        }
        $severalPagesConfig = $searchOpportunitiesEvaluation->opportunitiesConfig->severalPagesForQuery;
        $minImpressionsPerPage = SiteInsightsOpportunityRuleConfig::scaleToWindow($severalPagesConfig->minImpressionsPerPage, $searchOpportunitiesEvaluation->window->days);
        // method-local: the page rows of each query, and the query's impressions over them
        $pageRowsByQuery = [];
        $reportImpressionsByQuery = [];
        foreach ($queryPageRows->getElements() as $queryPageRow) {
            $query = (string)($queryPageRow->query ?? '');
            if (!isset($pageRowsByQuery[$query])) {
                $pageRowsByQuery[$query] = new SearchPerformanceRows();
                $pageRowsByQuery[$query]->dimensions = ['query', 'page'];
            }
            $pageRowsByQuery[$query]->add($queryPageRow);
            $reportImpressionsByQuery[$query] = ($reportImpressionsByQuery[$query] ?? 0) + $queryPageRow->metrics->impressions;
        }
        foreach ($this->takeTopRows($queryRows, $severalPagesConfig->topQueries)->getElements() as $queryRow) {
            $query = (string)($queryRow->query ?? '');
            $reportImpressions = $reportImpressionsByQuery[$query] ?? 0;
            if ($reportImpressions <= 0) {
                continue;
            }
            $passingPages = 0;
            foreach (isset($pageRowsByQuery[$query]) ? $pageRowsByQuery[$query]->getElements() : [] as $queryPageRow) {
                if ($queryPageRow->metrics->impressions >= $minImpressionsPerPage && $queryPageRow->metrics->impressions / $reportImpressions >= $severalPagesConfig->minShare) {
                    $passingPages++;
                }
            }
            if ($passingPages < max(1, $severalPagesConfig->minPages)) {
                continue;
            }
            $searchOpportunity = $this->createOpportunity(SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY, SearchOpportunity::SUBJECT_QUERY, $queryRow, $pageRowsByQuery[$query]);
            $kindOpportunities->add($searchOpportunity);
        }
        return $kindOpportunities;
    }

    /**
     * Rule 4: keys observed in both periods, by clicks (per day when the comparison is partial: the previous period's
     * clicks are normalised to the window's days). Rising: delta ≥ minAbsoluteDelta (scaled), delta ÷ max(previous, 1)
     * ≥ minRelativeDelta, max(current, previous) ≥ minClicksEitherPeriod (scaled); declining is the mirror. Ordered by
     * the absolute delta. Keys observed in one period only go to `observedInOnePeriodOnly`, never ranked.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchOpportunityKindResult $searchOpportunityKindResult
     * @return SearchOpportunities
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function findRisingAndDeclining(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchOpportunityKindResult $searchOpportunityKindResult): SearchOpportunities
    {
        $kind = $searchOpportunityKindResult->kind;
        $dimension = in_array($kind, [SearchOpportunity::KIND_RISING_PAGE, SearchOpportunity::KIND_DECLINING_PAGE], true) ? 'page' : 'query';
        $isRising = in_array($kind, [SearchOpportunity::KIND_RISING_QUERY, SearchOpportunity::KIND_RISING_PAGE], true);
        $kindOpportunities = new SearchOpportunities();
        $comparedRows = $dimension === 'page' ? $this->loadComparedPageRows($searchOpportunitiesEvaluation) : $this->loadComparedQueryRows($searchOpportunitiesEvaluation);
        $searchOpportunityKindResult->sourceCoverage = $comparedRows->coverage;
        if (isset($comparedRows->error)) {
            $searchOpportunityKindResult->error = $comparedRows->error;
            $searchOpportunityKindResult->isPartial = count($comparedRows->getElements()) > 0;
            if (!$searchOpportunityKindResult->isPartial) {
                $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_SOURCE_FAILED;
                return $kindOpportunities;
            }
        }
        if (!count($comparedRows->getElements())) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_NO_ROWS;
            return $kindOpportunities;
        }
        $comparisonWindow = $searchOpportunitiesEvaluation->comparisonWindow;
        $windowDays = $searchOpportunitiesEvaluation->window->days;
        $risingAndDecliningConfig = $searchOpportunitiesEvaluation->opportunitiesConfig->risingAndDeclining;
        $minClicksEitherPeriod = SiteInsightsOpportunityRuleConfig::scaleToWindow($risingAndDecliningConfig->minClicksEitherPeriod, $windowDays);
        $minAbsoluteDelta = SiteInsightsOpportunityRuleConfig::scaleToWindow($risingAndDecliningConfig->minAbsoluteDelta, $windowDays);
        $observedInOnePeriodOnly = new SearchPerformanceRows();
        $observedInOnePeriodOnly->dimensions = [$dimension];
        $hitRowsWithDelta = [];
        foreach ($comparedRows->getElements() as $comparedRow) {
            $searchMetricsComparison = $comparedRow->comparison ?? null;
            if ($searchMetricsComparison === null) {
                continue;
            }
            $currentClicks = $comparedRow->metrics->hasData ? $comparedRow->metrics->clicks : 0;
            $previousClicks = isset($searchMetricsComparison->previous) && $searchMetricsComparison->previous->hasData
                ? $searchMetricsComparison->previous->clicks * $windowDays / max(1, $comparisonWindow->days)
                : 0.0;
            if ($searchMetricsComparison->counterpartState === SearchMetricsComparison::COUNTERPART_NOT_OBSERVED) {
                // observed only in the window (rising) or only in the comparison window (declining)
                $observedInWindowOnly = $comparedRow->metrics->hasData;
                if ($observedInWindowOnly === $isRising && max($currentClicks, $previousClicks) >= $minClicksEitherPeriod) {
                    $onePeriodRow = clone $comparedRow;
                    $observedInOnePeriodOnly->add($onePeriodRow);
                }
                continue;
            }
            if ($searchMetricsComparison->counterpartState !== SearchMetricsComparison::COUNTERPART_OBSERVED) {
                continue;
            }
            $clicksDelta = $currentClicks - $previousClicks;
            $directedDelta = $isRising ? $clicksDelta : -$clicksDelta;
            if ($directedDelta < $minAbsoluteDelta
                || $directedDelta / max($previousClicks, 1.0) < $risingAndDecliningConfig->minRelativeDelta
                || max($currentClicks, $previousClicks) < $minClicksEitherPeriod
            ) {
                continue;
            }
            $hitRowsWithDelta[] = [$comparedRow, $directedDelta];
        }
        $searchOpportunityKindResult->observedInOnePeriodOnly = $observedInOnePeriodOnly;
        usort($hitRowsWithDelta, static fn (array $firstHit, array $secondHit): int => $secondHit[1] <=> $firstHit[1]);
        $subjectType = $dimension === 'page' ? SearchOpportunity::SUBJECT_PAGE : SearchOpportunity::SUBJECT_QUERY;
        foreach (array_slice($hitRowsWithDelta, 0, max(0, $risingAndDecliningConfig->maxResults)) as [$hitRow]) {
            // the comparison is the opportunity's own; the evidence row carries the window's metrics
            $evidenceRow = clone $hitRow;
            unset($evidenceRow->comparison);
            $searchOpportunity = $this->createOpportunity($kind, $subjectType, $hitRow, $this->createRowSet([$dimension], $evidenceRow));
            $searchOpportunity->comparison = $hitRow->comparison;
            $kindOpportunities->add($searchOpportunity);
        }
        return $kindOpportunities;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param string $kind
     * @return int the calls the kind adds: the sources it needs that no earlier kind has read
     */
    protected function planCallsOfKind(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, string $kind): int
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $opportunitiesConfig = $searchOpportunitiesEvaluation->opportunitiesConfig;
        $plannedPages = (int)ceil(max(1, $budgetsConfig->sortFetchCeiling) / max(1, $budgetsConfig->rowsPerCall));
        $queryRowsCalls = isset($searchOpportunitiesEvaluation->queryRowsOfWindow) ? 0 : $plannedPages;
        $counterpartCalls = 2 * $this->planChunkCount($opportunitiesConfig->risingAndDeclining->candidatesPerPeriod);
        return match ($kind) {
            SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY => $queryRowsCalls,
            SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION => isset($searchOpportunitiesEvaluation->queryDeviceRowsOfWindow) ? 0 : $plannedPages,
            SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY => $queryRowsCalls
                + (isset($searchOpportunitiesEvaluation->queryPageRowsOfTopQueries) ? 0 : $this->planChunkCount($opportunitiesConfig->severalPagesForQuery->topQueries)),
            SearchOpportunity::KIND_RISING_QUERY, SearchOpportunity::KIND_DECLINING_QUERY => isset($searchOpportunitiesEvaluation->comparedQueryRows)
                ? 0
                : $queryRowsCalls + 1 + $counterpartCalls,
            default => isset($searchOpportunitiesEvaluation->comparedPageRows) ? 0 : 2 + $counterpartCalls,
        };
    }

    /**
     * @param int $keyCount
     * @return int calls of a filter by `$keyCount` keys: ceil(keys ÷ counterpart.keysPerChunk), at most counterpart.maxChunks
     */
    protected function planChunkCount(int $keyCount): int
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        return min(max(1, $budgetsConfig->counterpartMaxChunks), (int)ceil(max(0, $keyCount) / max(1, $budgetsConfig->counterpartKeysPerChunk)));
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @return SearchPerformanceRows the queries of the window in provider order (read once per request)
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadQueryRowsOfWindow(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation): SearchPerformanceRows
    {
        $searchOpportunitiesEvaluation->queryRowsOfWindow ??= $this->fetchRowsToCeiling($searchOpportunitiesEvaluation, $searchOpportunitiesEvaluation->window, ['query']);
        return $searchOpportunitiesEvaluation->queryRowsOfWindow;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @return SearchPerformanceRows the query × device rows of the window in provider order (read once per request)
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadQueryDeviceRowsOfWindow(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation): SearchPerformanceRows
    {
        $searchOpportunitiesEvaluation->queryDeviceRowsOfWindow ??= $this->fetchRowsToCeiling($searchOpportunitiesEvaluation, $searchOpportunitiesEvaluation->window, ['query', 'device']);
        return $searchOpportunitiesEvaluation->queryDeviceRowsOfWindow;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchPerformanceRows $queryRows the queries of the window
     * @return SearchPerformanceRows the query × page rows of the top queries (chunks in one batch; failed chunks set
     *         `error`, the rows of the others stay)
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadQueryPageRowsOfTopQueries(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchPerformanceRows $queryRows): SearchPerformanceRows
    {
        if (isset($searchOpportunitiesEvaluation->queryPageRowsOfTopQueries)) {
            return $searchOpportunitiesEvaluation->queryPageRowsOfTopQueries;
        }
        $severalPagesConfig = $searchOpportunitiesEvaluation->opportunitiesConfig->severalPagesForQuery;
        $topQueries = $this->readDimensionValues($this->takeTopRows($queryRows, $severalPagesConfig->topQueries), 'query');
        $searchOpportunitiesEvaluation->queryPageRowsOfTopQueries = $this->loadRowsByKeys(
            $searchOpportunitiesEvaluation,
            $searchOpportunitiesEvaluation->window,
            ['query', 'page'],
            'query',
            $topQueries,
            max(1, $severalPagesConfig->pagesPerQueryMax),
            $this->planChunkCount($severalPagesConfig->topQueries)
        );
        return $searchOpportunitiesEvaluation->queryPageRowsOfTopQueries;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @return SearchPerformanceRows the candidate queries of both periods, each with its comparison
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadComparedQueryRows(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation): SearchPerformanceRows
    {
        if (!isset($searchOpportunitiesEvaluation->comparedQueryRows)) {
            $queryRows = $this->loadQueryRowsOfWindow($searchOpportunitiesEvaluation);
            $searchOpportunitiesEvaluation->comparedQueryRows = isset($queryRows->error)
                ? $queryRows
                : $this->compareCandidates($searchOpportunitiesEvaluation, 'query', $queryRows);
        }
        return $searchOpportunitiesEvaluation->comparedQueryRows;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @return SearchPerformanceRows the candidate pages of both periods, each with its comparison
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadComparedPageRows(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation): SearchPerformanceRows
    {
        if (!isset($searchOpportunitiesEvaluation->comparedPageRows)) {
            $candidatesPerPeriod = $searchOpportunitiesEvaluation->opportunitiesConfig->risingAndDeclining->candidatesPerPeriod;
            $pageRows = $this->loadTopRowsOfWindow($searchOpportunitiesEvaluation, $searchOpportunitiesEvaluation->window, 'page', $candidatesPerPeriod);
            $searchOpportunitiesEvaluation->comparedPageRows = isset($pageRows->error)
                ? $pageRows
                : $this->compareCandidates($searchOpportunitiesEvaluation, 'page', $pageRows);
        }
        return $searchOpportunitiesEvaluation->comparedPageRows;
    }

    /**
     * Candidates of both periods: the top `candidatesPerPeriod` keys of the window (from its rows) and of the
     * comparison window (one call); a key missing on one side is asked there by key (RE2 chunks). Each key becomes
     * one row of the window's metrics (without data when the window has none) with its comparison.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param string $dimension query or page
     * @param SearchPerformanceRows $windowRows the window's rows of the dimension in provider order
     * @return SearchPerformanceRows
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function compareCandidates(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, string $dimension, SearchPerformanceRows $windowRows): SearchPerformanceRows
    {
        $comparisonWindow = $searchOpportunitiesEvaluation->comparisonWindow;
        $candidatesPerPeriod = max(1, $searchOpportunitiesEvaluation->opportunitiesConfig->risingAndDeclining->candidatesPerPeriod);
        $chunkLimit = $this->planChunkCount($candidatesPerPeriod);
        $comparedRows = new SearchPerformanceRows();
        $comparedRows->dimensions = [$dimension];
        $comparedRows->coverage = clone $windowRows->coverage;
        $comparedRows->coverage->counterpartChunks = 0;
        // method-local: the rows of each side by key
        $windowRowsByKey = [];
        foreach ($windowRows->getElements() as $windowRow) {
            $windowRowsByKey[(string)$windowRow->getDimensionValue($dimension)] = $windowRow;
        }
        $windowCandidateKeys = $this->readDimensionValues($this->takeTopRows($windowRows, $candidatesPerPeriod), $dimension);
        $comparisonRows = $this->loadTopRowsOfWindow($searchOpportunitiesEvaluation, $comparisonWindow, $dimension, $candidatesPerPeriod);
        if (isset($comparisonRows->error)) {
            $comparedRows->error = $comparisonRows->error;
            return $comparedRows;
        }
        $comparisonRowsByKey = [];
        foreach ($comparisonRows->getElements() as $comparisonRow) {
            $comparisonRowsByKey[(string)$comparisonRow->getDimensionValue($dimension)] = $comparisonRow;
        }
        $comparisonCandidateKeys = array_keys($comparisonRowsByKey);
        $keysMissingInComparison = array_values(array_filter($windowCandidateKeys, static fn (string $key): bool => !isset($comparisonRowsByKey[$key])));
        $keysMissingInWindow = array_values(array_filter(array_map('strval', $comparisonCandidateKeys), static fn (string $key): bool => !isset($windowRowsByKey[$key])));
        $askedInComparison = [];
        $askedInWindow = [];
        foreach ([[$comparisonWindow, $keysMissingInComparison, true], [$searchOpportunitiesEvaluation->window, $keysMissingInWindow, false]] as [$askedWindow, $missingKeys, $isComparisonSide]) {
            if (!$missingKeys) {
                continue;
            }
            $counterpartRows = $this->loadRowsByKeys($searchOpportunitiesEvaluation, $askedWindow, [$dimension], $dimension, $missingKeys, 1, $chunkLimit);
            $comparedRows->coverage->counterpartChunks += $counterpartRows->coverage->counterpartChunks;
            $comparedRows->coverage->counterpartsNotRequested += $counterpartRows->coverage->counterpartsNotRequested;
            if (isset($counterpartRows->error)) {
                $comparedRows->error = $counterpartRows->error;
            }
            foreach ($counterpartRows->getElements() as $counterpartRow) {
                $counterpartKey = (string)$counterpartRow->getDimensionValue($dimension);
                if ($isComparisonSide) {
                    $comparisonRowsByKey[$counterpartKey] = $counterpartRow;
                } else {
                    $windowRowsByKey[$counterpartKey] = $counterpartRow;
                }
            }
            foreach ($this->findAskedKeys($missingKeys, $counterpartRows) as $askedKey) {
                if ($isComparisonSide) {
                    $askedInComparison[$askedKey] = true;
                } else {
                    $askedInWindow[$askedKey] = true;
                }
            }
        }
        $comparableDays = $comparisonWindow->days;
        foreach (array_values(array_unique([...$windowCandidateKeys, ...array_map('strval', $comparisonCandidateKeys)])) as $candidateKey) {
            $windowRow = $windowRowsByKey[$candidateKey] ?? null;
            $comparisonRow = $comparisonRowsByKey[$candidateKey] ?? null;
            $comparedRow = $windowRow !== null ? clone $windowRow : $this->createRowWithoutData($dimension, $candidateKey, $comparisonRow?->metrics->aggregation ?? SearchMetrics::AGGREGATION_BY_PROPERTY);
            if ($windowRow !== null && $comparisonRow !== null) {
                $comparedRow->comparison = SearchMetricsComparison::createFromMetrics($windowRow->metrics, $comparisonRow->metrics, $searchOpportunitiesEvaluation->window->days, $comparableDays, $comparisonWindow->comparisonIsPartial);
            } elseif ($windowRow !== null) {
                $comparedRow->comparison = SearchMetricsComparison::createWithoutCounterpart(
                    $windowRow->metrics,
                    isset($askedInComparison[$candidateKey]) ? SearchMetricsComparison::COUNTERPART_NOT_OBSERVED : SearchMetricsComparison::COUNTERPART_NOT_REQUESTED
                );
            } else {
                $searchMetricsComparison = SearchMetricsComparison::createWithoutCounterpart(
                    $comparedRow->metrics,
                    isset($askedInWindow[$candidateKey]) ? SearchMetricsComparison::COUNTERPART_NOT_OBSERVED : SearchMetricsComparison::COUNTERPART_NOT_REQUESTED
                );
                $searchMetricsComparison->previous = $comparisonRow->metrics;
                $comparedRow->comparison = $searchMetricsComparison;
            }
            $comparedRows->add($comparedRow);
        }
        $comparedRows->coverage->fetchedRows = count($comparedRows->getElements());
        return $comparedRows;
    }

    /**
     * @param string[] $requestedKeys
     * @param SearchPerformanceRows $counterpartRows
     * @return string[] the keys of the request that were actually asked (the rest stayed beyond the chunk limits)
     */
    protected function findAskedKeys(array $requestedKeys, SearchPerformanceRows $counterpartRows): array
    {
        return array_slice($requestedKeys, 0, max(0, count($requestedKeys) - $counterpartRows->coverage->counterpartsNotRequested));
    }

    /**
     * Rows of `$dimensions` filtered by keys of `$keyDimension`: RE2 exact alternations in chunks of at most
     * counterpart.keysPerChunk keys and counterpart.regexMaxLength characters, at most `$chunkLimit` chunks, all in
     * one batch; keys beyond the chunks are counted in `coverage.counterpartsNotRequested`.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchWindow $searchWindow
     * @param string[] $dimensions
     * @param string $keyDimension
     * @param string[] $keys
     * @param int $rowsPerKey
     * @param int $chunkLimit
     * @return SearchPerformanceRows the rows of every chunk that answered; `error` from a chunk that failed
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadRowsByKeys(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchWindow $searchWindow, array $dimensions, string $keyDimension, array $keys, int $rowsPerKey, int $chunkLimit): SearchPerformanceRows
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $keysPerChunk = max(1, $budgetsConfig->counterpartKeysPerChunk);
        $regexMaxLength = max(16, $budgetsConfig->counterpartRegexMaxLength);
        // method-local: the keys of each chunk
        $keyChunks = [];
        $currentChunk = [];
        foreach ($keys as $key) {
            $nextChunk = [...$currentChunk, $key];
            if ($currentChunk && (count($nextChunk) > $keysPerChunk || strlen(SearchRegex::buildExactAlternation($nextChunk)) > $regexMaxLength)) {
                $keyChunks[] = $currentChunk;
                $nextChunk = [$key];
            }
            $currentChunk = $nextChunk;
        }
        if ($currentChunk) {
            $keyChunks[] = $currentChunk;
        }
        $askedChunks = array_slice($keyChunks, 0, max(0, $chunkLimit));
        $keysNotRequested = count($keys) - array_sum(array_map('count', $askedChunks));
        $aggregationType = SearchQuestion::AGGREGATION_AUTO;
        $chunkQuestions = [];
        $searchQuestions = new SearchQuestions();
        foreach ($askedChunks as $keyChunk) {
            $keyFilter = SearchQuestionFilter::create($keyDimension, 'includingRegex', SearchRegex::buildExactAlternation($keyChunk));
            $chunkQuestion = $this->buildQuestion($searchWindow, $dimensions, $this->createFilterSet($keyFilter), $aggregationType, $rowsPerKey * count($keyChunk));
            $chunkQuestions[] = $chunkQuestion;
            $searchQuestions->add($chunkQuestion);
        }
        $chunkRows = new SearchPerformanceRows();
        $chunkRows->dimensions = $dimensions;
        $chunkRows->coverage = new SearchRowsCoverage();
        $chunkRows->coverage->counterpartsNotRequested = max(0, $keysNotRequested);
        if (!$chunkQuestions) {
            return $chunkRows;
        }
        $searchReports = $this->getGoogleSearchConsoleSourceService()->loadReports($searchOpportunitiesEvaluation->insightsSite, $searchQuestions);
        $chunkRows->coverage->counterpartChunks = count($chunkQuestions);
        $isFirstAnsweredChunk = true;
        foreach ($chunkQuestions as $chunkQuestion) {
            $answeredRows = $this->findReport($searchReports, $chunkQuestion)->performanceRows;
            $searchOpportunitiesEvaluation->providerCalls += $answeredRows->coverage->providerCalls;
            $chunkRows->coverage->providerCalls += $answeredRows->coverage->providerCalls;
            if (isset($answeredRows->error)) {
                $chunkRows->error ??= $answeredRows->error;
                continue;
            }
            $this->mergeCoverage($chunkRows->coverage, $answeredRows->coverage, $isFirstAnsweredChunk);
            $isFirstAnsweredChunk = false;
            foreach ($answeredRows->getElements() as $answeredRow) {
                $chunkRows->add($answeredRow);
            }
        }
        $chunkRows->coverage->fetchedRows = count($chunkRows->getElements());
        return $chunkRows;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchWindow $searchWindow
     * @param string $dimension
     * @param int $rowLimit
     * @return SearchPerformanceRows the top rows of one dimension in provider order (one call)
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function loadTopRowsOfWindow(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchWindow $searchWindow, string $dimension, int $rowLimit): SearchPerformanceRows
    {
        $topQuestion = $this->buildQuestion($searchWindow, [$dimension], new SearchQuestionFilters(), SearchQuestion::AGGREGATION_AUTO, max(1, $rowLimit));
        $topRows = $this->findReport(
            $this->getGoogleSearchConsoleSourceService()->loadReports($searchOpportunitiesEvaluation->insightsSite, $this->createQuestionSet($topQuestion)),
            $topQuestion
        )->performanceRows;
        $searchOpportunitiesEvaluation->providerCalls += $topRows->coverage->providerCalls;
        return $topRows;
    }

    /**
     * Pages of `budgets.rowsPerCall` rows from row 0 up to `budgets.sortFetchCeiling` in provider order (clicks
     * descending); rows already seen are dropped and counted. A failed page fails the source.
     *
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param SearchWindow $searchWindow
     * @param string[] $dimensions
     * @return SearchPerformanceRows
     * @throws BadRequestException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function fetchRowsToCeiling(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, SearchWindow $searchWindow, array $dimensions): SearchPerformanceRows
    {
        $budgetsConfig = $this->getSiteInsightsConfigService()->getBudgetsConfig();
        $rowsPerCall = max(1, min($budgetsConfig->rowsPerCall, GoogleSearchConsoleSourceService::PROVIDER_MAX_ROW_LIMIT));
        $fetchCeiling = max($rowsPerCall, $budgetsConfig->sortFetchCeiling);
        $fetchedRows = new SearchPerformanceRows();
        $fetchedRows->dimensions = $dimensions;
        $fetchedRows->coverage = new SearchRowsCoverage();
        $isFirstPage = true;
        for ($startRow = 0; $startRow < $fetchCeiling; $startRow += $rowsPerCall) {
            $rowLimit = min($rowsPerCall, $fetchCeiling - $startRow);
            $pageQuestion = $this->buildQuestion($searchWindow, $dimensions, new SearchQuestionFilters(), SearchQuestion::AGGREGATION_AUTO, $rowLimit, $startRow);
            $pageRows = $this->findReport(
                $this->getGoogleSearchConsoleSourceService()->loadReports($searchOpportunitiesEvaluation->insightsSite, $this->createQuestionSet($pageQuestion)),
                $pageQuestion
            )->performanceRows;
            $searchOpportunitiesEvaluation->providerCalls += $pageRows->coverage->providerCalls;
            $fetchedRows->coverage->providerCalls += $pageRows->coverage->providerCalls;
            if (isset($pageRows->error)) {
                $fetchedRows->error = $pageRows->error;
                return $fetchedRows;
            }
            $this->mergeCoverage($fetchedRows->coverage, $pageRows->coverage, $isFirstPage);
            $isFirstPage = false;
            $pageRowCount = count($pageRows->getElements());
            foreach ($pageRows->getElements() as $pageRow) {
                if ($fetchedRows->getByUniqueKey($pageRow->uniqueKey()) !== null) {
                    $fetchedRows->coverage->duplicateRowsDropped++;
                    continue;
                }
                $fetchedRows->add($pageRow);
            }
            if ($pageRowCount < $rowLimit) {
                $fetchedRows->coverage->providerRowsExhausted = true;
                break;
            }
            if ($startRow + $rowsPerCall >= $fetchCeiling) {
                $fetchedRows->coverage->limitReached = true;
            }
        }
        $fetchedRows->coverage->rowLimitPerCall = $rowsPerCall;
        $fetchedRows->coverage->fetchedRows = count($fetchedRows->getElements());
        $fetchedRows->coverage->sortScope = SearchRowsCoverage::SORT_SCOPE_FETCHED_ROWS;
        return $fetchedRows;
    }

    /**
     * @param SearchRowsCoverage $targetCoverage
     * @param SearchRowsCoverage $answeredCoverage
     * @param bool $isFirstAnswer
     * @return void the answer's aggregation, cache state and oldest answer time merged into the target
     */
    protected function mergeCoverage(SearchRowsCoverage $targetCoverage, SearchRowsCoverage $answeredCoverage, bool $isFirstAnswer): void
    {
        foreach (['requestedAggregation', 'responseAggregation'] as $aggregationPropertyName) {
            if (isset($answeredCoverage->$aggregationPropertyName)) {
                $targetCoverage->$aggregationPropertyName = $answeredCoverage->$aggregationPropertyName;
            }
        }
        $targetCoverage->responseAggregationKnown = $answeredCoverage->responseAggregationKnown;
        $targetCoverage->anonymizedQueriesOmitted = $targetCoverage->anonymizedQueriesOmitted || $answeredCoverage->anonymizedQueriesOmitted;
        $targetCoverage->joinedDimensionsMayLoseRows = $targetCoverage->joinedDimensionsMayLoseRows || $answeredCoverage->joinedDimensionsMayLoseRows;
        $targetCoverage->rowLimitPerCall = max($targetCoverage->rowLimitPerCall, $answeredCoverage->rowLimitPerCall);
        $targetCoverage->fromCache = $isFirstAnswer ? $answeredCoverage->fromCache : $targetCoverage->fromCache && $answeredCoverage->fromCache;
        if (isset($answeredCoverage->fetchedAt) && (!isset($targetCoverage->fetchedAt) || $answeredCoverage->fetchedAt->getTimestamp() < $targetCoverage->fetchedAt->getTimestamp())) {
            $targetCoverage->fetchedAt = $answeredCoverage->fetchedAt;
        }
    }

    /**
     * @param SearchPerformanceRows $sourceRows
     * @param SearchOpportunityKindResult $searchOpportunityKindResult
     * @return bool whether the kind can be evaluated on the rows (else its error or emptyReason is set)
     */
    protected function acceptSource(SearchPerformanceRows $sourceRows, SearchOpportunityKindResult $searchOpportunityKindResult): bool
    {
        $searchOpportunityKindResult->sourceCoverage = $sourceRows->coverage;
        if (isset($sourceRows->error)) {
            $searchOpportunityKindResult->error = $sourceRows->error;
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_SOURCE_FAILED;
            return false;
        }
        if (!count($sourceRows->getElements())) {
            $searchOpportunityKindResult->emptyReason = SearchOpportunityKindResult::EMPTY_NO_ROWS;
            return false;
        }
        return true;
    }

    /**
     * @param SearchPerformanceRows $searchPerformanceRows rows in provider order
     * @param int $count
     * @return SearchPerformanceRows the first `$count` rows (no metadata)
     */
    protected function takeTopRows(SearchPerformanceRows $searchPerformanceRows, int $count): SearchPerformanceRows
    {
        $topRows = new SearchPerformanceRows();
        $topRows->dimensions = $searchPerformanceRows->dimensions;
        foreach (array_slice(array_values($searchPerformanceRows->getElements()), 0, max(0, $count)) as $topRow) {
            $topRows->add($topRow);
        }
        return $topRows;
    }

    /**
     * @param SearchPerformanceRows $searchPerformanceRows
     * @param string $dimension
     * @return string[] the rows' values of the dimension, in order
     */
    protected function readDimensionValues(SearchPerformanceRows $searchPerformanceRows, string $dimension): array
    {
        return array_values(array_map(
            static fn (SearchPerformanceRow $searchPerformanceRow): string => (string)$searchPerformanceRow->getDimensionValue($dimension),
            $searchPerformanceRows->getElements()
        ));
    }

    /**
     * @param string $kind
     * @param string $subjectType
     * @param SearchPerformanceRow $subjectRow the row whose dimensions and metrics name the subject
     * @param SearchPerformanceRows $evidenceRows the rows the result is based on (copied)
     * @return SearchOpportunity
     */
    protected function createOpportunity(string $kind, string $subjectType, SearchPerformanceRow $subjectRow, SearchPerformanceRows $evidenceRows): SearchOpportunity
    {
        $searchOpportunity = new SearchOpportunity();
        $searchOpportunity->kind = $kind;
        $searchOpportunity->subjectType = $subjectType;
        foreach (['query', 'page'] as $subjectDimension) {
            $subjectValue = $subjectRow->getDimensionValue($subjectDimension);
            if ($subjectValue !== null) {
                $searchOpportunity->$subjectDimension = $subjectValue;
            }
        }
        $searchOpportunity->metrics = $subjectRow->metrics;
        $searchOpportunity->evidenceRows = new SearchPerformanceRows();
        $searchOpportunity->evidenceRows->dimensions = $evidenceRows->dimensions;
        foreach ($evidenceRows->getElements() as $evidenceRow) {
            $evidenceRowCopy = clone $evidenceRow;
            $searchOpportunity->evidenceRows->add($evidenceRowCopy);
        }
        return $searchOpportunity;
    }

    /**
     * @param string[] $dimensions
     * @param SearchPerformanceRow $searchPerformanceRow
     * @return SearchPerformanceRows a set of the one row (no metadata)
     */
    protected function createRowSet(array $dimensions, SearchPerformanceRow $searchPerformanceRow): SearchPerformanceRows
    {
        $rowSet = new SearchPerformanceRows();
        $rowSet->dimensions = $dimensions;
        $rowSet->add($searchPerformanceRow);
        return $rowSet;
    }

    /**
     * @param string $dimension
     * @param string $key
     * @param string $aggregation
     * @return SearchPerformanceRow a row of the key without data in the window
     */
    protected function createRowWithoutData(string $dimension, string $key, string $aggregation): SearchPerformanceRow
    {
        $searchPerformanceRow = new SearchPerformanceRow();
        $searchPerformanceRow->$dimension = $key;
        $searchPerformanceRow->metrics = SearchMetrics::createWithoutData($aggregation);
        return $searchPerformanceRow;
    }

    /**
     * @param float|null $position
     * @return int|null the index of the position bucket of the rounded-down position; null from 11 on or without one
     */
    protected function findPositionBucketIndex(?float $position): ?int
    {
        if ($position === null) {
            return null;
        }
        $roundedDownPosition = floor($position);
        for ($bucketIndex = 0; $bucketIndex < count(self::POSITION_BUCKET_BOUNDS) - 1; $bucketIndex++) {
            if ($roundedDownPosition >= self::POSITION_BUCKET_BOUNDS[$bucketIndex] && $roundedDownPosition < self::POSITION_BUCKET_BOUNDS[$bucketIndex + 1]) {
                return $bucketIndex;
            }
        }
        return null;
    }

    /**
     * @param InsightsSite $insightsSite
     * @param string $query
     * @return bool whether the query matches one of the site's brand patterns (RE2 patterns, evaluated with PCRE)
     */
    protected function isBrandQuery(InsightsSite $insightsSite, string $query): bool
    {
        foreach ($insightsSite->brandQueryPatterns as $brandQueryPattern) {
            if (preg_match('~' . str_replace('~', '\\~', $brandQueryPattern) . '~u', $query) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation
     * @param string $kind
     * @return SearchOpportunityThresholds the kind's thresholds as applied (scaled to the window)
     */
    protected function buildThresholds(SearchOpportunitiesEvaluation $searchOpportunitiesEvaluation, string $kind): SearchOpportunityThresholds
    {
        $windowDays = $searchOpportunitiesEvaluation->window->days;
        $opportunitiesConfig = $searchOpportunitiesEvaluation->opportunitiesConfig;
        $searchOpportunityThresholds = new SearchOpportunityThresholds();
        $searchOpportunityThresholds->windowDays = $windowDays;
        switch ($kind) {
            case SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY:
                $strikingDistanceConfig = $opportunitiesConfig->strikingDistanceQuery;
                $searchOpportunityThresholds->positionFrom = $strikingDistanceConfig->positionFrom;
                $searchOpportunityThresholds->positionTo = $strikingDistanceConfig->positionTo;
                $searchOpportunityThresholds->minImpressions = SiteInsightsOpportunityRuleConfig::scaleToWindow($strikingDistanceConfig->minImpressions, $windowDays);
                $searchOpportunityThresholds->excludeBrand = $strikingDistanceConfig->excludeBrand;
                $searchOpportunityThresholds->maxResults = $strikingDistanceConfig->maxResults;
                break;
            case SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION:
                $weakClickRateConfig = $opportunitiesConfig->weakClickRateForPosition;
                $searchOpportunityThresholds->minImpressions = SiteInsightsOpportunityRuleConfig::scaleToWindow($weakClickRateConfig->minImpressions, $windowDays);
                $searchOpportunityThresholds->minRowsPerBucket = $weakClickRateConfig->minRowsPerBucket;
                $searchOpportunityThresholds->ratio = $weakClickRateConfig->ratio;
                $searchOpportunityThresholds->excludeBrand = $weakClickRateConfig->excludeBrand;
                $searchOpportunityThresholds->fallbackToAllDevices = $weakClickRateConfig->fallbackToAllDevices;
                $searchOpportunityThresholds->maxResults = $weakClickRateConfig->maxResults;
                break;
            case SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY:
                $severalPagesConfig = $opportunitiesConfig->severalPagesForQuery;
                $searchOpportunityThresholds->topQueries = $severalPagesConfig->topQueries;
                $searchOpportunityThresholds->minImpressionsPerPage = SiteInsightsOpportunityRuleConfig::scaleToWindow($severalPagesConfig->minImpressionsPerPage, $windowDays);
                $searchOpportunityThresholds->minShare = $severalPagesConfig->minShare;
                $searchOpportunityThresholds->minPages = $severalPagesConfig->minPages;
                break;
            default:
                $risingAndDecliningConfig = $opportunitiesConfig->risingAndDeclining;
                $searchOpportunityThresholds->candidatesPerPeriod = $risingAndDecliningConfig->candidatesPerPeriod;
                $searchOpportunityThresholds->minClicksEitherPeriod = SiteInsightsOpportunityRuleConfig::scaleToWindow($risingAndDecliningConfig->minClicksEitherPeriod, $windowDays);
                $searchOpportunityThresholds->minAbsoluteDelta = SiteInsightsOpportunityRuleConfig::scaleToWindow($risingAndDecliningConfig->minAbsoluteDelta, $windowDays);
                $searchOpportunityThresholds->minRelativeDelta = $risingAndDecliningConfig->minRelativeDelta;
                $searchOpportunityThresholds->maxResults = $risingAndDecliningConfig->maxResults;
        }
        return $searchOpportunityThresholds;
    }

    /** @return string[] the kinds that compare two periods */
    protected function getComparisonKinds(): array
    {
        return [SearchOpportunity::KIND_RISING_QUERY, SearchOpportunity::KIND_DECLINING_QUERY, SearchOpportunity::KIND_RISING_PAGE, SearchOpportunity::KIND_DECLINING_PAGE];
    }
}
