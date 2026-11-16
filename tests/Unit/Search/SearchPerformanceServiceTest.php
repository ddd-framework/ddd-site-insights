<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticSearchAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestConfig;

/**
 * Search performance on synthetic answers computed from each question: request graph, counterparts, paging and
 * sorting, page groups, details, export, access and validation. No network, no real data.
 */
class SearchPerformanceServiceTest extends SiteInsightsTestCase
{
    protected SyntheticSearchAnalytics $syntheticSearchAnalytics;

    protected AnsweringNetwork $answeringNetwork;

    protected function setUp(): void
    {
        parent::setUp();
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $this->syntheticSearchAnalytics = new SyntheticSearchAnalytics();
        $this->answeringNetwork = AnsweringNetwork::install(fn (array $requestBody) => $this->syntheticSearchAnalytics->answer($requestBody));
    }

    public function testOverviewAsksTheRequestGraphInOneBatch(): void
    {
        $this->syntheticSearchAnalytics->daysWithoutData = ['2026-10-20'];

        $overview = $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest(SearchComparisonWindow::KIND_PREVIOUS_PERIOD));

        $dataBodies = $this->answeringNetwork->getSentDataBodies();
        self::assertCount(8, $dataBodies, 'totals, series, top queries, top pages, countries, devices, totals and series of the comparison');
        self::assertCount(8, array_unique(array_map('json_encode', $dataBodies)), 'distinct questions');
        self::assertContains([
            'startDate' => '2026-10-17', 'endDate' => '2026-11-13', 'type' => 'web', 'dataState' => 'final',
            'aggregationType' => 'byProperty', 'rowLimit' => 1, 'startRow' => 0,
        ], $dataBodies, 'totals of the window');
        self::assertContains([
            'startDate' => '2026-09-19', 'endDate' => '2026-10-16', 'type' => 'web', 'dataState' => 'final',
            'aggregationType' => 'byProperty', 'rowLimit' => 1, 'startRow' => 0,
        ], $dataBodies, 'totals of the previous period');

        self::assertSame('2026-11-13', SearchDays::fromDate($overview->dataThroughDate));
        self::assertSame('probe', $overview->dataThroughDateSource);
        self::assertSame('America/Los_Angeles', $overview->window->dayTimeZone);
        self::assertSame(28, $overview->window->days);
        self::assertSame('2026-09-19', SearchDays::fromDate($overview->comparisonWindow->from));
        self::assertSame(200, $overview->totals->metrics->clicksDelta, 'totals are their own question (1000 against 800)');
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $overview->totals->metrics->counterpartState);
        self::assertSame(SearchMetrics::AGGREGATION_BY_PROPERTY, $overview->totals->metrics->current->aggregation);

        $seriesRows = array_values($overview->series->getElements());
        self::assertCount(28, $seriesRows, 'every day of the window exactly once');
        self::assertSame('2026-10-20', SearchDays::fromDate($seriesRows[3]->date));
        self::assertTrue($seriesRows[3]->isFilledDay);
        self::assertFalse($seriesRows[3]->metrics->hasData);
        self::assertFalse($seriesRows[0]->isFilledDay);
        self::assertSame('2026-09-19', SearchDays::fromDate($seriesRows[0]->comparison->previousDate), 'aligned by offset');

        self::assertCount(25, $overview->topQueries->getElements());
        self::assertTrue($overview->topQueries->coverage->anonymizedQueriesOmitted);
        self::assertFalse(isset($overview->topQueries->getElements()[0]->comparison), 'top lists carry no counterparts');
        self::assertSame(SearchMetrics::AGGREGATION_BY_PAGE, $overview->topPages->coverage->responseAggregation);
        self::assertSame('2026-10-17', SearchDays::fromDate($overview->devices->window->from));
        self::assertSame(5, $overview->devices->coverage->rowLimitPerCall);
    }

    public function testOverviewAnswersFromTheCacheTheSecondTime(): void
    {
        $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest());
        $sentRequestsAfterFirstRead = $this->answeringNetwork->countSentRequests();

        $overview = $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest());

        self::assertSame($sentRequestsAfterFirstRead, $this->answeringNetwork->countSentRequests());
        self::assertTrue($overview->topQueries->coverage->fromCache);
        self::assertSame(0, $overview->topQueries->coverage->providerCalls);
    }

    public function testAnEmptyComparisonPeriodIsNotObservedNeverZero(): void
    {
        $this->syntheticSearchAnalytics->comparisonWindowIsEmpty = true;

        $overview = $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest(SearchComparisonWindow::KIND_PREVIOUS_PERIOD));

        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_OBSERVED, $overview->totals->metrics->counterpartState);
        self::assertFalse(isset($overview->totals->metrics->previous));
        self::assertFalse(isset($overview->totals->metrics->clicksDelta));
        self::assertSame(0, $overview->totals->comparisonCoverage->fetchedRows);
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_OBSERVED, $overview->series->first()->comparison->counterpartState);
    }

    public function testRaisingTheCredentialsRevisionMissesTheCache(): void
    {
        $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest());
        $sentRequestsWithRevisionOne = $this->answeringNetwork->countSentRequests();
        TestConfig::apply(['credentials' => ['entries' => [TestConfig::CREDENTIALS_ENTRY_NAME => ['envName' => TestConfig::KEY_ENVIRONMENT_VARIABLE, 'revision' => 2]]]]);

        $overview = $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest());

        self::assertSame(2 * $sentRequestsWithRevisionOne, $this->answeringNetwork->countSentRequests(), 'the same questions asked again');
        self::assertFalse($overview->topQueries->coverage->fromCache);
    }

    public function testAListSortedByClicksHasTheCounterpartOfEveryRow(): void
    {
        $this->syntheticSearchAnalytics->keysMissingInComparison = ['synthetic widget 003'];
        $searchListRequest = $this->createListRequest('query', SearchComparisonWindow::KIND_PREVIOUS_PERIOD);
        $searchListRequest->limit = 10;

        $searchPerformanceRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        $rows = array_values($searchPerformanceRows->getElements());
        self::assertCount(10, $rows);
        self::assertSame(SearchRowsCoverage::SORT_SCOPE_PROVIDER, $searchPerformanceRows->coverage->sortScope);
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $rows[0]->comparison->counterpartState);
        self::assertSame(10, $rows[0]->comparison->clicksDelta);
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_OBSERVED, $rows[3]->comparison->counterpartState, 'never shown as zero');
        self::assertFalse(isset($rows[3]->comparison->clicksDelta));
        self::assertSame(1, $searchPerformanceRows->coverage->counterpartChunks);
        self::assertSame(0, $searchPerformanceRows->coverage->counterpartsNotRequested);
        self::assertSame(SearchComparisonWindow::KIND_PREVIOUS_PERIOD, $searchPerformanceRows->comparisonWindow->kind);

        $counterpartBody = $this->findBodyOfWindowStarting('2026-09-19');
        self::assertSame(10, $counterpartBody['rowLimit']);
        $counterpartFilter = $counterpartBody['dimensionFilterGroups'][0]['filters'][0];
        self::assertSame('includingRegex', $counterpartFilter['operator']);
        self::assertStringStartsWith('^(?:synthetic widget 000|synthetic widget 001|', $counterpartFilter['expression']);
        self::assertStringEndsWith('|synthetic widget 009)$', $counterpartFilter['expression']);
    }

    public function testCounterpartChunksStopAtTheirLimitsAndALongKeyIsAskedAlone(): void
    {
        TestConfig::apply(['budgets' => ['counterpart' => ['keysPerChunk' => 4, 'maxChunks' => 2, 'regexMaxLength' => 4000]]]);
        $searchListRequest = $this->createListRequest('query', SearchComparisonWindow::KIND_PREVIOUS_PERIOD);
        $searchListRequest->limit = 10;

        $searchPerformanceRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        $rows = array_values($searchPerformanceRows->getElements());
        self::assertSame(2, $searchPerformanceRows->coverage->counterpartChunks);
        self::assertSame(2, $searchPerformanceRows->coverage->counterpartsNotRequested);
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $rows[7]->comparison->counterpartState);
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_REQUESTED, $rows[8]->comparison->counterpartState);

        TestConfig::apply(['budgets' => ['counterpart' => ['keysPerChunk' => 100, 'maxChunks' => 5, 'regexMaxLength' => 20]]]);
        $searchListRequest->limit = 2;
        $searchListRequest->offset = 20;
        $longKeyRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);
        self::assertSame(2, $longKeyRows->coverage->counterpartChunks);
        $equalsBodies = array_filter(
            $this->answeringNetwork->getSentDataBodies(),
            static fn (array $requestBody): bool => ($requestBody['dimensionFilterGroups'][0]['filters'][0]['operator'] ?? null) === 'equals'
        );
        self::assertCount(2, $equalsBodies, 'a key longer than the expression limit is asked with equals');
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $longKeyRows->first()->comparison->counterpartState);
    }

    public function testAListSortedByImpressionsPagesUpToTheCeilingAndSortsItself(): void
    {
        $this->syntheticSearchAnalytics->availableRows = 3000;
        $searchListRequest = $this->createListRequest('query');
        $searchListRequest->sortBy = SearchListRequest::SORT_BY_IMPRESSIONS;
        $searchListRequest->limit = 5;
        $searchListRequest->offset = 2;

        $searchPerformanceRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        $dataBodies = $this->answeringNetwork->getSentDataBodies();
        self::assertSame([0, 2500], array_column($dataBodies, 'startRow'), 'two sequential pages');
        $coverage = $searchPerformanceRows->coverage;
        self::assertSame(SearchRowsCoverage::SORT_SCOPE_FETCHED_ROWS, $coverage->sortScope);
        self::assertSame(3000, $coverage->fetchedRows);
        self::assertTrue($coverage->providerRowsExhausted, 'the second page was short');
        self::assertFalse($coverage->limitReached);
        self::assertSame(2, $coverage->providerCalls);
        $impressions = array_map(static fn (SearchPerformanceRow $searchPerformanceRow): int => $searchPerformanceRow->metrics->impressions, array_values($searchPerformanceRows->getElements()));
        self::assertSame([1005, 1005, 1005, 1005, 1005], $impressions, 'the highest impressions, from the third row on');
        $clicks = array_map(static fn (SearchPerformanceRow $searchPerformanceRow): int => $searchPerformanceRow->metrics->clicks, array_values($searchPerformanceRows->getElements()));
        self::assertGreaterThanOrEqual($clicks[1], $clicks[0], 'ties: clicks descending');

        $this->syntheticSearchAnalytics->availableRows = 6000;
        $searchListRequest->dataState = SearchWindowRequest::DATA_STATE_ALL;
        $ceilingRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);
        self::assertTrue($ceilingRows->coverage->limitReached, 'stopped at the ceiling while the last page was full');
        self::assertFalse($ceilingRows->coverage->providerRowsExhausted);
    }

    public function testPositionSortsAscendingByDefault(): void
    {
        $searchListRequest = $this->createListRequest('page');
        $searchListRequest->sortBy = SearchListRequest::SORT_BY_POSITION;
        $searchListRequest->limit = 3;

        $searchPerformanceRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        $positions = array_map(static fn (SearchPerformanceRow $searchPerformanceRow): ?float => $searchPerformanceRow->metrics->position ?? null, array_values($searchPerformanceRows->getElements()));
        self::assertSame([1.0, 1.5, 2.0], $positions);
    }

    public function testCountriesAreComparedWithOneCall(): void
    {
        $this->syntheticSearchAnalytics->keysMissingInComparison = ['fra'];
        $searchListRequest = $this->createListRequest('country', SearchComparisonWindow::KIND_PREVIOUS_PERIOD);
        $searchListRequest->device = 'mobile';

        $searchPerformanceRows = $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        self::assertSame(1, $searchPerformanceRows->coverage->counterpartChunks);
        $countryBody = $this->findBodyOfWindowStarting('2026-10-17');
        self::assertSame('byProperty', $countryBody['aggregationType']);
        self::assertSame(['dimension' => 'device', 'operator' => 'equals', 'expression' => 'MOBILE'], $countryBody['dimensionFilterGroups'][0]['filters'][0]);
        $rowsByCountry = [];
        foreach ($searchPerformanceRows->getElements() as $searchPerformanceRow) {
            $rowsByCountry[$searchPerformanceRow->country] = $searchPerformanceRow;
        }
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_OBSERVED, $rowsByCountry['fra']->comparison->counterpartState);
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $rowsByCountry['deu']->comparison->counterpartState);
    }

    public function testAPageGroupFilterForcesAutoAggregation(): void
    {
        $searchListRequest = $this->createListRequest('query');
        $searchListRequest->pageGroup = 'shop';

        $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);

        $listBody = $this->answeringNetwork->getSentDataBodies()[0];
        self::assertSame('auto', $listBody['aggregationType']);
        self::assertSame('^https://example\\.test(?:(?:/shop/)|(?:/cart$))', $listBody['dimensionFilterGroups'][0]['filters'][0]['expression']);
    }

    public function testPageGroupsAreAskedOnePerGroupAndNeverSummed(): void
    {
        $searchPageGroupPerformances = $this->getSearchPerformanceService()->getPageGroups(TestConfig::SITE_KEY, $this->createWindowRequest(SearchComparisonWindow::KIND_PREVIOUS_YEAR));

        self::assertCount(4, $this->answeringNetwork->getSentDataBodies(), 'window and comparison per group');
        self::assertTrue($searchPageGroupPerformances->groupsMayOverlap);
        self::assertSame(SearchMetrics::AGGREGATION_BY_PAGE, $searchPageGroupPerformances->aggregation);
        $guidesPerformance = $searchPageGroupPerformances->first();
        self::assertSame('guides', $guidesPerformance->pageGroup->key);
        self::assertSame('^https://example\\.test(?:(?:/guides/))', $guidesPerformance->pageExpression);
        self::assertSame(SearchMetrics::AGGREGATION_BY_PAGE, $guidesPerformance->totals->coverage->responseAggregation);
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $guidesPerformance->totals->metrics->counterpartState);
        self::assertSame('2025-10-18', SearchDays::fromDate($searchPageGroupPerformances->comparisonWindow->from), '52 weeks earlier');
    }

    public function testPageDetail(): void
    {
        $searchPageDetail = $this->getSearchPerformanceService()->getPageDetail(TestConfig::SITE_KEY, ' https://example.test/guides/item-001/ ', $this->createWindowRequest(SearchComparisonWindow::KIND_PREVIOUS_PERIOD));

        self::assertSame('https://example.test/guides/item-001/', $searchPageDetail->page);
        self::assertSame('guides', $searchPageDetail->pageGroup->key);
        self::assertCount(5, $this->answeringNetwork->getSentDataBodies());
        self::assertCount(28, $searchPageDetail->series->getElements());
        self::assertSame(['synthetic widget 000'], [$searchPageDetail->queries->first()->query]);
        foreach ($this->answeringNetwork->getSentDataBodies() as $dataBody) {
            self::assertSame('auto', $dataBody['aggregationType']);
            self::assertSame(['dimension' => 'page', 'operator' => 'equals', 'expression' => 'https://example.test/guides/item-001/'], $dataBody['dimensionFilterGroups'][0]['filters'][0]);
        }

        $this->expectException(BadRequestException::class);
        $this->getSearchPerformanceService()->getPageDetail(TestConfig::SITE_KEY, 'https://other.example.com/', $this->createWindowRequest());
    }

    public function testQueryDetail(): void
    {
        $searchQueryDetail = $this->getSearchPerformanceService()->getQueryDetail(TestConfig::SITE_KEY, 'synthetic widget 002', $this->createWindowRequest());

        self::assertSame('synthetic widget 002', $searchQueryDetail->query);
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_REQUESTED, $searchQueryDetail->totals->metrics->counterpartState);
        self::assertTrue(isset($searchQueryDetail->pages->first()->page));
        foreach ($this->answeringNetwork->getSentDataBodies() as $dataBody) {
            self::assertSame(['dimension' => 'query', 'operator' => 'equals', 'expression' => 'synthetic widget 002'], $dataBody['dimensionFilterGroups'][0]['filters'][0]);
        }
        $aggregationTypes = array_column($this->answeringNetwork->getSentDataBodies(), 'aggregationType');
        sort($aggregationTypes);
        self::assertSame(['auto', 'byProperty', 'byProperty'], $aggregationTypes, 'pages auto, totals and series byProperty');
    }

    public function testExportPagesEachDayUntilAShortPageAndCachesNothing(): void
    {
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 20]]);
        $searchExportRequest = new SearchExportRequest();
        $searchExportRequest->from = SearchDays::toDate('2026-11-10');
        $searchExportRequest->to = SearchDays::toDate('2026-11-12');
        $writtenRows = [];

        $searchExportSummary = $this->getSearchPerformanceService()->export(
            TestConfig::SITE_KEY,
            $searchExportRequest,
            static function (SearchPerformanceRow $searchPerformanceRow) use (&$writtenRows): void {
                $writtenRows[] = $searchPerformanceRow;
            }
        );

        self::assertTrue($searchExportSummary->byDay, 'query and page together: by day');
        self::assertSame(90, $searchExportSummary->rowsWritten);
        self::assertCount(90, $writtenRows);
        self::assertSame(6, $searchExportSummary->providerCalls, 'two pages per day');
        self::assertSame('2026-11-12', SearchDays::fromDate($searchExportSummary->lastFinishedDay));
        self::assertTrue($searchExportSummary->joinedDimensionsMayLoseRows);
        self::assertTrue($searchExportSummary->days->first()->providerRowsExhausted);
        $exportBodies = $this->answeringNetwork->getSentDataBodies();
        self::assertSame(['2026-11-10', '2026-11-10', '2026-11-11', '2026-11-11', '2026-11-12', '2026-11-12'], array_column($exportBodies, 'startDate'));
        self::assertSame([0, 20, 0, 20, 0, 20], array_column($exportBodies, 'startRow'));
        self::assertStringNotContainsString('synthetic widget', $this->getRedisCache()->dumpAsText() . $this->getApcCache()->dumpAsText(), 'export rows are never cached');

        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 20, 'callsPerOperation' => ['export' => 3]]]);
        $limitedSummary = $this->getSearchPerformanceService()->export(TestConfig::SITE_KEY, $searchExportRequest, static fn () => null);
        self::assertTrue($limitedSummary->limitReached);
        self::assertSame(3, $limitedSummary->providerCalls);
        self::assertSame('2026-11-10', SearchDays::fromDate($limitedSummary->lastFinishedDay));
    }

    public function testAnAccountThatMayNotViewGetsNothingAndNothingIsAsked(): void
    {
        $nonAdministratorAccount = TestAccounts::nonAdmin();
        AuthService::instance()->setAccount($nonAdministratorAccount);

        $this->expectException(ForbiddenException::class);
        try {
            $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, $this->createWindowRequest());
        } finally {
            self::assertSame(0, $this->answeringNetwork->countSentRequests());
        }
    }

    /** @return array[] setting, value */
    public static function provideInvalidListRequests(): array
    {
        return [
            'limit 0' => ['limit', 0],
            'limit above 1000' => ['limit', 1001],
            'offset above 24999' => ['offset', 25000],
            'unknown sort' => ['sortBy', 'name'],
            'unknown direction' => ['sortDirection', 'up'],
            'country of two letters' => ['country', 'de'],
            'unknown device' => ['device', 'watch'],
            'unknown page group' => ['pageGroup', 'unknown'],
            'empty query filter' => ['queryContains', ''],
            'unknown dimension' => ['dimension', 'date'],
        ];
    }

    /** @dataProvider provideInvalidListRequests */
    public function testInvalidListRequestsAreRefused(string $settingName, mixed $value): void
    {
        $searchListRequest = $this->createListRequest('query');
        $searchListRequest->$settingName = $value;

        $this->expectException(BadRequestException::class);
        $this->getSearchPerformanceService()->getRows(TestConfig::SITE_KEY, $searchListRequest);
    }

    public function testAPartialComparisonComparesPerDay(): void
    {
        $currentMetrics = SearchMetrics::create(280, 2800, 5.0, SearchMetrics::AGGREGATION_BY_PROPERTY);
        $previousMetrics = SearchMetrics::create(70, 1400, 6.0, SearchMetrics::AGGREGATION_BY_PROPERTY);

        $searchMetricsComparison = SearchMetricsComparison::createFromMetrics($currentMetrics, $previousMetrics, 28, 14, true);

        self::assertTrue($searchMetricsComparison->perDay);
        self::assertFalse(isset($searchMetricsComparison->clicksDelta));
        self::assertEqualsWithDelta(5.0, $searchMetricsComparison->clicksPerDayDelta, 1e-9);
        self::assertEqualsWithDelta(0.0, $searchMetricsComparison->impressionsPerDayDelta, 1e-9);
        self::assertEqualsWithDelta(0.05, $searchMetricsComparison->ctrDelta, 1e-9);
        self::assertEqualsWithDelta(-1.0, $searchMetricsComparison->positionDelta, 1e-9);
    }

    protected function createWindowRequest(string $compare = SearchWindowRequest::COMPARE_NONE): SearchWindowRequest
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->compare = $compare;
        return $searchWindowRequest;
    }

    protected function createListRequest(string $dimension, string $compare = SearchWindowRequest::COMPARE_NONE): SearchListRequest
    {
        $searchListRequest = new SearchListRequest();
        $searchListRequest->dimension = $dimension;
        $searchListRequest->compare = $compare;
        return $searchListRequest;
    }

    protected function findBodyOfWindowStarting(string $startDay): array
    {
        foreach ($this->answeringNetwork->getSentDataBodies() as $dataBody) {
            if ($dataBody['startDate'] === $startDay) {
                return $dataBody;
            }
        }
        self::fail('No call asked a window starting ' . $startDay);
    }

    protected function getSearchPerformanceService(): SearchPerformanceService
    {
        return DDDService::instance()->getService(SearchPerformanceService::class);
    }
}
