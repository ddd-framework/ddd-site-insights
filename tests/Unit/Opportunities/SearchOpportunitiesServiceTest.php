<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Opportunities;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchClickRateBaseline;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunities;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunitiesRequest;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunity;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunityKindResult;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchPositionBucket;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Services\SearchOpportunitiesService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\InMemoryCache;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticOpportunityAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestConfig;

/** The opportunity rules on invented rows: hits and near misses at each threshold, empty reasons, budget, config */
class SearchOpportunitiesServiceTest extends SiteInsightsTestCase
{
    public const string WINDOW = SyntheticOpportunityAnalytics::PERIOD_WINDOW;
    public const string COMPARISON = SyntheticOpportunityAnalytics::PERIOD_COMPARISON;

    protected SyntheticOpportunityAnalytics $syntheticOpportunityAnalytics;

    protected AnsweringNetwork $answeringNetwork;

    protected function setUp(): void
    {
        parent::setUp();
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $this->syntheticOpportunityAnalytics = new SyntheticOpportunityAnalytics();
        $this->answeringNetwork = AnsweringNetwork::install(fn (array $requestBody) => $this->syntheticOpportunityAnalytics->answer($requestBody));
    }

    public function testStrikingDistanceQueriesWithNearMissesAtEachThreshold(): void
    {
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query'], [
            [['alpha guide'], 40, 500, 9.0],
            [['beta guide'], 30, 900, 25.0],          // position above positionTo
            [['gamma guide'], 20, 99, 12.0],          // one impression short
            [['example shop'], 15, 900, 10.0],        // brand
            [['delta guide'], 10, 100, 20.0],         // on both bounds
            [['epsilon guide'], 9, 800, 7.9],         // position below positionFrom
        ]);

        $searchOpportunities = $this->getOpportunities(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY);

        $kindResult = $searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY);
        self::assertSame(2, $kindResult->resultCount);
        self::assertSame(['alpha guide', 'delta guide'], $this->findSubjects($searchOpportunities), 'ordered by impressions');
        self::assertSame(100, $kindResult->thresholds->minImpressions);
        self::assertSame(28, $kindResult->thresholds->windowDays);
        self::assertFalse(isset($kindResult->thresholds->ratio), 'only the rule\'s own thresholds');
        self::assertSame(6, $kindResult->sourceCoverage->fetchedRows);
        self::assertTrue($kindResult->sourceCoverage->providerRowsExhausted);
        self::assertSame(1, $kindResult->providerCalls);
        $firstOpportunity = $searchOpportunities->first();
        self::assertSame(SearchOpportunity::SUBJECT_QUERY, $firstOpportunity->subjectType);
        self::assertCount(1, $firstOpportunity->evidenceRows->getElements());
        self::assertSame('alpha guide', $firstOpportunity->evidenceRows->first()->query);
        self::assertNotSame('', $kindResult->note);
    }

    public function testThresholdsAreScaledToTheWindow(): void
    {
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query'], [[['short window guide'], 3, 25, 9.0]]);
        $searchOpportunitiesRequest = new SearchOpportunitiesRequest();
        $searchOpportunitiesRequest->preset = SearchOpportunitiesRequest::PRESET_7_DAYS;
        $searchOpportunitiesRequest->kind = SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY;

        $searchOpportunities = $this->getSearchOpportunitiesService()->getOpportunities(TestConfig::SITE_KEY, $searchOpportunitiesRequest);

        $kindResult = $searchOpportunities->kinds->first();
        self::assertSame(7, $kindResult->thresholds->windowDays);
        self::assertSame(25, $kindResult->thresholds->minImpressions, 'ceil(100 × 7 / 28)');
        self::assertSame(1, $kindResult->resultCount);
    }

    public function testWeakClickRateWithTheDeviceBaselineAndTheAllDevicesFallback(): void
    {
        $queryDeviceRows = [];
        for ($rowIndex = 0; $rowIndex < 5; $rowIndex++) {
            $queryDeviceRows[] = [['mobile query ' . $rowIndex, 'MOBILE'], 30, 100, 1.4];
        }
        $queryDeviceRows[] = [['weak mobile query', 'MOBILE'], 5, 100, 1.6];      // 5 % against the other rows' 27.2 %
        $queryDeviceRows[] = [['near mobile query', 'MOBILE'], 13, 100, 1.2];     // 13 % ≥ half of the other rows' 25.8 %
        $queryDeviceRows[] = [['weak desktop query', 'DESKTOP'], 2, 100, 1.5];    // the desktop bucket has 2 rows
        $queryDeviceRows[] = [['desktop query', 'DESKTOP'], 30, 100, 1.0];
        $queryDeviceRows[] = [['tiny mobile query', 'MOBILE'], 0, 49, 1.0];       // below minImpressions
        $queryDeviceRows[] = [['example weak', 'MOBILE'], 0, 500, 1.0];           // brand
        $queryDeviceRows[] = [['deep query', 'MOBILE'], 0, 500, 11.0];            // not judged from position 11 on
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query', 'device'], $queryDeviceRows);

        $searchOpportunities = $this->getOpportunities(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION);

        self::assertSame(['weak desktop query', 'weak mobile query'], $this->findSubjects($searchOpportunities), 'ordered by missing clicks');
        [$desktopHit, $mobileHit] = array_values($searchOpportunities->getElements());
        self::assertSame('mobile', $mobileHit->device);
        self::assertSame(SearchClickRateBaseline::SCOPE_DEVICE, $mobileHit->baseline->baselineScope);
        self::assertSame(6, $mobileHit->baseline->rowCount, 'the bucket without the row itself');
        self::assertEqualsWithDelta(163 / 600, $mobileHit->baseline->clickRate, 0.0001);
        self::assertEqualsWithDelta(100 * (163 / 600 - 0.05), $mobileHit->missingClicks, 0.01);
        self::assertSame(SearchClickRateBaseline::SCOPE_ALL_DEVICES, $desktopHit->baseline->baselineScope);
        self::assertSame(8, $desktopHit->baseline->rowCount);
        self::assertEqualsWithDelta(100 * (198 / 800 - 0.02), $desktopHit->missingClicks, 0.01);
        $kindResult = $searchOpportunities->kinds->first();
        self::assertSame(50, $kindResult->thresholds->minImpressions);
        self::assertSame(5, $kindResult->thresholds->minRowsPerBucket);
        self::assertSame(0.005, $kindResult->thresholds->minBaselineClickRate);
        self::assertTrue($kindResult->thresholds->fallbackToAllDevices);
        $desktopBucket = $kindResult->positionBuckets->findBucket('desktop', 1.0);
        self::assertSame(2, $desktopBucket->rowCount);
        self::assertFalse($desktopBucket->hasBaseline);
        self::assertSame(SearchPositionBucket::NO_BASELINE_TOO_FEW_ROWS, $desktopBucket->noBaselineReason);
        self::assertTrue($kindResult->positionBuckets->findBucket(SearchPositionBucket::DEVICE_ALL, 1.0)->hasBaseline);
        self::assertCount(4 * 5, $kindResult->positionBuckets->getElements(), 'three devices and all, five buckets each');
    }

    public function testASiteSwitchesTheFallbackOffAndAThinBaselineNamesItsBuckets(): void
    {
        $siteWithOwnThresholds = TestConfig::getDefaultSites()[0];
        $siteWithOwnThresholds['opportunities'] = ['weakClickRateForPosition' => ['fallbackToAllDevices' => false, 'minRowsPerBucket' => 3]];
        TestConfig::apply(['sites' => ['sites' => [$siteWithOwnThresholds]]]);
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query', 'device'], [
            [['desktop one', 'DESKTOP'], 30, 100, 1.0],
            [['desktop two', 'DESKTOP'], 30, 100, 1.0],
            [['desktop three', 'DESKTOP'], 30, 100, 1.0],
            [['weak desktop', 'DESKTOP'], 1, 100, 1.0],
            [['weak mobile', 'MOBILE'], 1, 100, 1.0],
            [['mobile one', 'MOBILE'], 30, 100, 1.0],
        ]);

        $searchOpportunities = $this->getOpportunities(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION);

        self::assertSame(['weak desktop'], $this->findSubjects($searchOpportunities), 'the mobile bucket (2 rows) is thin and the fallback is off');
        self::assertSame(3, $searchOpportunities->kinds->first()->thresholds->minRowsPerBucket);

        $siteWithOwnThresholds['opportunities'] = ['weakClickRateForPosition' => ['minRowsPerBucket' => 10]];
        TestConfig::apply(['sites' => ['sites' => [$siteWithOwnThresholds]]]);
        $thinOpportunities = $this->getOpportunities(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION);
        $thinKindResult = $thinOpportunities->kinds->first();
        self::assertSame(SearchOpportunityKindResult::EMPTY_THIN_BASELINE, $thinKindResult->emptyReason);
        self::assertSame(0, $thinKindResult->resultCount);
        self::assertSame(4, $thinKindResult->positionBuckets->findBucket('desktop', 1.0)->rowCount);
        self::assertSame(2, $thinKindResult->positionBuckets->findBucket('mobile', 1.0)->rowCount);
        self::assertSame(6, $thinKindResult->positionBuckets->findBucket(SearchPositionBucket::DEVICE_ALL, 1.0)->rowCount);
        self::assertSame(2.0, $thinKindResult->positionBuckets->findBucket('mobile', 1.0)->positionTo);
    }

    public function testADominantZeroClickRowNeitherLowersItsOwnBaselineNorLeadsTheList(): void
    {
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query', 'device'], [
            // [1,2): one large row without clicks pulls the bucket below the lowest usable baseline
            [['dominant query', 'MOBILE'], 0, 3246, 1.7],
            [['quiet query 1', 'MOBILE'], 0, 100, 1.2],
            [['quiet query 2', 'MOBILE'], 1, 100, 1.3],
            [['quiet query 3', 'MOBILE'], 0, 100, 1.4],
            [['quiet query 4', 'MOBILE'], 0, 100, 1.5],
            [['quiet query 5', 'MOBILE'], 0, 100, 1.6],
            // [2,3): a real baseline of about 1.3 %
            [['steady query 1', 'MOBILE'], 2, 150, 2.1],
            [['steady query 2', 'MOBILE'], 2, 150, 2.2],
            [['steady query 3', 'MOBILE'], 2, 150, 2.3],
            [['steady query 4', 'MOBILE'], 2, 150, 2.4],
            [['steady query 5', 'MOBILE'], 2, 150, 2.5],
            [['steady query 6', 'MOBILE'], 2, 150, 2.6],
            [['short of clicks query', 'MOBILE'], 4, 800, 2.5],    // 0.5 % against 1.2 %
            [['no click query', 'MOBILE'], 0, 100, 2.7],           // 0 clicks on few impressions
        ]);

        $searchOpportunities = $this->getOpportunities(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION);

        self::assertSame(['short of clicks query', 'no click query'], $this->findSubjects($searchOpportunities), 'ordered by missing clicks; nothing of the dominant bucket');
        [$shortOfClicksHit, $noClickHit] = array_values($searchOpportunities->getElements());
        self::assertEqualsWithDelta(12 / 1000, $shortOfClicksHit->baseline->clickRate, 0.00001, 'the other rows of [2,3)');
        self::assertEqualsWithDelta(800 * (12 / 1000 - 0.005), $shortOfClicksHit->missingClicks, 0.01);
        self::assertGreaterThan($noClickHit->missingClicks, $shortOfClicksHit->missingClicks);
        $kindResult = $searchOpportunities->kinds->first();
        $dominantBucket = $kindResult->positionBuckets->findBucket('mobile', 1.0);
        self::assertSame(6, $dominantBucket->rowCount);
        self::assertFalse($dominantBucket->hasBaseline);
        self::assertSame(SearchPositionBucket::NO_BASELINE_BELOW_MIN_BASELINE_CLICK_RATE, $dominantBucket->noBaselineReason);
        self::assertTrue($kindResult->positionBuckets->findBucket('mobile', 2.0)->hasBaseline);

        // only the dominant bucket: no usable baseline anywhere, and the answer says why
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query', 'device'], [
            [['dominant query', 'MOBILE'], 0, 3246, 1.7],
            [['quiet query 1', 'MOBILE'], 0, 100, 1.2],
            [['quiet query 2', 'MOBILE'], 1, 100, 1.3],
            [['quiet query 3', 'MOBILE'], 0, 100, 1.4],
            [['quiet query 4', 'MOBILE'], 0, 100, 1.5],
            [['quiet query 5', 'MOBILE'], 0, 100, 1.6],
        ]);
        // the same question again: a new answer, not the cached one
        $this->caches = InMemoryCache::installForCacheGroups();
        $lowBaselineResult = $this->getOpportunities(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION)->kinds->first();
        self::assertSame(SearchOpportunityKindResult::EMPTY_BASELINE_BELOW_MIN_CLICK_RATE, $lowBaselineResult->emptyReason);
        self::assertSame(0, $lowBaselineResult->resultCount);
    }

    public function testSeveralPagesForATopQueryAskedInOneFilteredReport(): void
    {
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query'], [
            [['alpha guide'], 50, 600, 3.0],
            [['beta guide'], 40, 1000, 3.0],
            [['gamma guide'], 30, 300, 3.0],
        ]);
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query', 'page'], [
            [['alpha guide', 'https://example.test/a/'], 30, 300, 3.0],
            [['alpha guide', 'https://example.test/b/'], 20, 200, 4.0],
            [['alpha guide', 'https://example.test/c/'], 0, 19, 9.0],          // below minImpressionsPerPage
            [['beta guide', 'https://example.test/a/'], 38, 950, 3.0],
            [['beta guide', 'https://example.test/d/'], 2, 50, 8.0],          // share 5 %
            [['gamma guide', 'https://example.test/e/'], 30, 300, 3.0],
        ]);

        $searchOpportunities = $this->getOpportunities(SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY);

        self::assertSame(['alpha guide'], $this->findSubjects($searchOpportunities));
        self::assertCount(3, $searchOpportunities->first()->evidenceRows->getElements(), 'the query\'s page rows');
        self::assertSame(600, $searchOpportunities->first()->metrics->impressions, 'the query\'s own row');
        $queryPageBodies = $this->syntheticOpportunityAnalytics->findBodiesWithDimensions('query', 'page');
        self::assertCount(1, $queryPageBodies, 'one chunk');
        self::assertSame(['dimension' => 'query', 'operator' => 'includingRegex', 'expression' => '^(?:alpha guide|beta guide|gamma guide)$'], $queryPageBodies[0]['dimensionFilterGroups'][0]['filters'][0]);
        self::assertSame(30, $queryPageBodies[0]['rowLimit'], 'pagesPerQueryMax × keys');
        $kindResult = $searchOpportunities->kinds->first();
        self::assertTrue($kindResult->sourceCoverage->joinedDimensionsMayLoseRows);
        self::assertSame(2, $kindResult->providerCalls);
    }

    public function testRisingAndDecliningFromBothPeriodsWithCounterpartsByKey(): void
    {
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query'], [
            [['rising guide'], 100, 1000, 4.0],
            [['steady guide'], 50, 800, 4.0],
            [['newcomer guide'], 40, 400, 6.0],
            [['falling guide'], 20, 600, 9.0],
            [['small guide'], 8, 100, 9.0],
        ]);
        $this->syntheticOpportunityAnalytics->setRows(self::COMPARISON, ['query'], [
            [['falling guide'], 80, 700, 5.0],
            [['gone guide'], 60, 500, 5.0],
            [['rising guide'], 50, 900, 6.0],
            [['steady guide'], 48, 800, 4.0],
            [['small guide'], 2, 100, 9.0],
        ]);

        $searchOpportunities = $this->getOpportunities();

        $risingResult = $searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_RISING_QUERY);
        $decliningResult = $searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_DECLINING_QUERY);
        self::assertSame(1, $risingResult->resultCount);
        self::assertSame(1, $decliningResult->resultCount);
        $risingOpportunity = $this->findOpportunityOfKind($searchOpportunities, SearchOpportunity::KIND_RISING_QUERY);
        self::assertSame('rising guide', $risingOpportunity->query);
        self::assertSame(50, $risingOpportunity->comparison->clicksDelta);
        self::assertSame(SearchMetricsComparison::COUNTERPART_OBSERVED, $risingOpportunity->comparison->counterpartState);
        self::assertSame('falling guide', $this->findOpportunityOfKind($searchOpportunities, SearchOpportunity::KIND_DECLINING_QUERY)->query);
        self::assertSame(['newcomer guide'], array_map(static fn ($row) => $row->query, array_values($risingResult->observedInOnePeriodOnly->getElements())));
        self::assertSame(['gone guide'], array_map(static fn ($row) => $row->query, array_values($decliningResult->observedInOnePeriodOnly->getElements())));
        $goneRow = $decliningResult->observedInOnePeriodOnly->first();
        self::assertFalse($goneRow->metrics->hasData);
        self::assertSame(SearchMetricsComparison::COUNTERPART_NOT_OBSERVED, $goneRow->comparison->counterpartState);
        self::assertSame(60, $goneRow->comparison->previous->clicks);
        self::assertSame(SearchComparisonWindow::KIND_PREVIOUS_PERIOD, $searchOpportunities->comparisonWindow->kind);

        // the counterparts: "newcomer" asked in the comparison window, "gone" asked in the window
        $counterpartBodies = array_values(array_filter(
            $this->syntheticOpportunityAnalytics->findBodiesWithDimensions('query'),
            static fn (array $requestBody): bool => isset($requestBody['dimensionFilterGroups'])
        ));
        self::assertCount(2, $counterpartBodies);
        $counterpartExpressions = array_map(static fn (array $requestBody): string => $requestBody['dimensionFilterGroups'][0]['filters'][0]['expression'], $counterpartBodies);
        sort($counterpartExpressions);
        self::assertSame(['^(?:gone guide)$', '^(?:newcomer guide)$'], $counterpartExpressions);
        self::assertSame(0, $decliningResult->providerCalls, 'the declining kind reuses the sources of the rising one');
        self::assertSame(2, $risingResult->sourceCoverage->counterpartChunks);
    }

    public function testAKindBeyondTheCallBudgetIsReturnedWithBudgetRefused(): void
    {
        TestConfig::apply(['budgets' => ['callsPerOperation' => ['opportunities' => 4]]]);
        $this->syntheticOpportunityAnalytics->setRows(self::WINDOW, ['query'], [[['alpha guide'], 40, 500, 9.0]]);

        $searchOpportunities = $this->getOpportunities();

        self::assertSame(SearchOpportunity::KINDS, array_map(static fn ($kindResult) => $kindResult->kind, array_values($searchOpportunities->kinds->getElements())), 'no kind left out');
        self::assertFalse(isset($searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY)->error));
        $weakClickRateResult = $searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_WEAK_CLICK_RATE_FOR_POSITION);
        self::assertSame(SiteInsightsErrorCause::BUDGET_REFUSED->value, $weakClickRateResult->error->cause);
        self::assertSame([], $this->syntheticOpportunityAnalytics->findBodiesWithDimensions('query', 'device'), 'nothing asked for a refused kind');
        self::assertFalse(isset($searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY)->error), 'its source is shared: one call more');
        self::assertSame(SiteInsightsErrorCause::BUDGET_REFUSED->value, $searchOpportunities->kinds->findKindResult(SearchOpportunity::KIND_RISING_PAGE)->error->cause);
        self::assertSame(4, $searchOpportunities->callsAllowed);
        self::assertLessThanOrEqual(3, $searchOpportunities->providerCalls, 'the probe is cached; the plan counts it as one');
    }

    public function testEmptyReasonsWindowTooShortNoComparisonAndSourceFailed(): void
    {
        $siteWithOwnThresholds = TestConfig::getDefaultSites()[0];
        $siteWithOwnThresholds['opportunities'] = ['minWindowDays' => 30];
        TestConfig::apply(['sites' => ['sites' => [$siteWithOwnThresholds]]]);
        $tooShortOpportunities = $this->getOpportunities();
        foreach ($tooShortOpportunities->kinds->getElements() as $kindResult) {
            self::assertSame(SearchOpportunityKindResult::EMPTY_WINDOW_TOO_SHORT, $kindResult->emptyReason, $kindResult->kind);
        }
        self::assertSame([], $this->syntheticOpportunityAnalytics->dataRequestBodies);

        TestConfig::apply(['sites' => ['sites' => TestConfig::getDefaultSites()]]);
        $earlyWindowRequest = new SearchOpportunitiesRequest();
        $earlyWindowRequest->from = SearchDays::toDate('2025-07-22');
        $earlyWindowRequest->to = SearchDays::toDate('2025-08-18');
        $earlyWindowRequest->kind = SearchOpportunity::KIND_RISING_QUERY;
        $noComparisonOpportunities = $this->getSearchOpportunitiesService()->getOpportunities(TestConfig::SITE_KEY, $earlyWindowRequest);
        self::assertSame(SearchOpportunityKindResult::EMPTY_NO_COMPARISON, $noComparisonOpportunities->kinds->first()->emptyReason);

        $this->syntheticOpportunityAnalytics->failingStatusByDimensions = ['query' => 500];
        $failedOpportunities = $this->getOpportunities(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY);
        $failedKindResult = $failedOpportunities->kinds->first();
        self::assertSame(SearchOpportunityKindResult::EMPTY_SOURCE_FAILED, $failedKindResult->emptyReason);
        self::assertSame(SiteInsightsErrorCause::PROVIDER_FAILED->value, $failedKindResult->error->cause);

        $this->syntheticOpportunityAnalytics->failingStatusByDimensions = [];
        $noRowsOpportunities = $this->getOpportunities(SearchOpportunity::KIND_STRIKING_DISTANCE_QUERY);
        self::assertSame(SearchOpportunityKindResult::EMPTY_NO_ROWS, $noRowsOpportunities->kinds->first()->emptyReason);
    }

    public function testInvalidKindsSettingsAndAccessAreRefused(): void
    {
        try {
            $this->getOpportunities('everything');
            self::fail('an unknown kind is refused');
        } catch (BadRequestException $badRequestException) {
            self::assertStringContainsString('kind must be one of', $badRequestException->getMessage());
        }

        $siteWithTypo = TestConfig::getDefaultSites()[0];
        $siteWithTypo['opportunities'] = ['weakClickRateForPosition' => ['minRowPerBucket' => 3]];
        TestConfig::apply(['sites' => ['sites' => [$siteWithTypo]]]);
        try {
            $this->getOpportunities();
            self::fail('an unknown setting is refused');
        } catch (InternalErrorException $internalErrorException) {
            self::assertStringContainsString('SiteInsights.sites[0].opportunities.weakClickRateForPosition.minRowPerBucket', $internalErrorException->getMessage());
        }
        TestConfig::apply(['sites' => ['sites' => TestConfig::getDefaultSites()], 'opportunities' => ['strikingDistanceQuery' => ['minImpressions' => '100']]]);
        try {
            $this->getOpportunities();
            self::fail('a setting of the wrong type is refused');
        } catch (InternalErrorException $internalErrorException) {
            self::assertStringContainsString('SiteInsights.opportunities.strikingDistanceQuery.minImpressions must be int', $internalErrorException->getMessage());
        }

        TestConfig::apply(['opportunities' => require dirname(__DIR__, 3) . '/config/app/SiteInsights/opportunities.php']);
        $nonAdministratorAccount = TestAccounts::nonAdmin();
        AuthService::instance()->setAccount($nonAdministratorAccount);
        $this->expectException(ForbiddenException::class);
        try {
            $this->getOpportunities();
        } finally {
            self::assertSame([], $this->syntheticOpportunityAnalytics->dataRequestBodies);
        }
    }

    protected function getOpportunities(?string $kind = null): SearchOpportunities
    {
        $searchOpportunitiesRequest = new SearchOpportunitiesRequest();
        if ($kind !== null) {
            $searchOpportunitiesRequest->kind = $kind;
        }
        return $this->getSearchOpportunitiesService()->getOpportunities(TestConfig::SITE_KEY, $searchOpportunitiesRequest);
    }

    /** @return string[] the queries or pages of the results, in order */
    protected function findSubjects(SearchOpportunities $searchOpportunities): array
    {
        return array_values(array_map(static fn (SearchOpportunity $searchOpportunity): string => $searchOpportunity->query ?? $searchOpportunity->page ?? '', $searchOpportunities->getElements()));
    }

    protected function findOpportunityOfKind(SearchOpportunities $searchOpportunities, string $kind): ?SearchOpportunity
    {
        foreach ($searchOpportunities->getElements() as $searchOpportunity) {
            if ($searchOpportunity->kind === $kind) {
                return $searchOpportunity;
            }
        }
        return null;
    }

    protected function getSearchOpportunitiesService(): SearchOpportunitiesService
    {
        return DDDService::instance()->getService(SearchOpportunitiesService::class);
    }
}
