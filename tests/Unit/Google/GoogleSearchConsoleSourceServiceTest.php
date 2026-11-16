<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Google;

use DDD\Domain\Base\Repo\Argus\Utils\ArgusApiOperation;
use DDD\Domain\Base\Repo\Argus\Utils\ArgusCache;
use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Google\GoogleApiFailure;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Tests\SiteInsights\Support\CapturingNetwork;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\SearchQuestionFactory;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestConfig;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;

/**
 * The Google source on synthetic answers: mapping, error causes, distinct questions, cache reads with the module's own
 * freshness check, the batch-wide read failure of an oversized answer, the 401 retry, budgets and the probe.
 */
class GoogleSearchConsoleSourceServiceTest extends SiteInsightsTestCase
{
    public function testMapRows(): void
    {
        CapturingNetwork::install(GoogleFixtures::response('searchanalytics-queries'), GoogleFixtures::response('searchanalytics-pages'));
        $queriesQuestion = SearchQuestionFactory::create(['device', 'query']);
        $pagesQuestion = SearchQuestionFactory::create(['page'], rowLimit: 1);

        $searchReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($queriesQuestion, $pagesQuestion));

        $queryRows = $searchReports->findReportForQuestion($queriesQuestion)->performanceRows;
        self::assertSame(['query', 'device'], $queryRows->dimensions, 'the canonical order');
        $firstRow = $queryRows->getElements()[0];
        self::assertSame('alpha widget', $firstRow->query);
        self::assertSame('desktop', $firstRow->device, 'devices in lower case');
        self::assertSame(40, $firstRow->metrics->clicks);
        self::assertSame(900, $firstRow->metrics->impressions);
        self::assertEqualsWithDelta(40 / 900, $firstRow->metrics->ctr, 1e-12, 'recomputed from the integers');
        self::assertSame(4.2, $firstRow->metrics->position);
        self::assertFalse(isset($queryRows->getElements()[2]->metrics->position));
        self::assertSame(0.0, $queryRows->getElements()[2]->metrics->ctr);
        self::assertSame(3, $queryRows->coverage->fetchedRows);
        self::assertTrue($queryRows->coverage->providerRowsExhausted);
        self::assertFalse($queryRows->coverage->limitReached);
        self::assertTrue($queryRows->coverage->anonymizedQueriesOmitted);
        self::assertFalse($queryRows->coverage->joinedDimensionsMayLoseRows);
        self::assertFalse($queryRows->coverage->responseAggregationKnown, 'Google answered "auto"');
        self::assertSame(SearchMetrics::AGGREGATION_BY_PROPERTY, $queryRows->coverage->responseAggregation);
        self::assertSame(1, $queryRows->coverage->providerCalls);
        self::assertFalse($queryRows->coverage->fromCache);

        $pageRows = $searchReports->findReportForQuestion($pagesQuestion)->performanceRows;
        self::assertSame(SearchMetrics::AGGREGATION_BY_PAGE, $pageRows->coverage->responseAggregation);
        self::assertTrue($pageRows->coverage->responseAggregationKnown);
        self::assertSame(SearchMetrics::AGGREGATION_BY_PAGE, $pageRows->getElements()[0]->metrics->aggregation);
        self::assertTrue($pageRows->coverage->limitReached, 'the page was full');
        self::assertSame('https://example.test/guides/alpha/', $pageRows->getElements()[0]->page);
    }

    /** @return array[] fixture, status, expected cause */
    public static function provideErrorAnswers(): array
    {
        return [
            '401' => ['error-401', 401, SiteInsightsErrorCause::TOKEN_REFUSED],
            '403 permission' => ['error-403-permission', 403, SiteInsightsErrorCause::NOT_USER_OF_PROPERTY],
            '403 service disabled' => ['error-403-service-disabled', 403, SiteInsightsErrorCause::API_NOT_ENABLED],
            '429' => ['error-429', 429, SiteInsightsErrorCause::QUOTA_EXCEEDED],
            '500' => ['error-500', 500, SiteInsightsErrorCause::PROVIDER_FAILED],
            '400' => ['error-400-filter', 400, SiteInsightsErrorCause::INVALID_REQUEST],
        ];
    }

    /** @dataProvider provideErrorAnswers */
    public function testClassifyFailure(string $fixtureName, int $statusCode, SiteInsightsErrorCause $expectedCause): void
    {
        $argusApiOperation = (new ReflectionClass(ArgusApiOperation::class))->newInstanceWithoutConstructor();
        $argusApiOperation->responseStatusCode = $statusCode;

        $googleApiFailure = GoogleApiFailure::fromAnswer(json_decode(GoogleFixtures::read($fixtureName)), $argusApiOperation);

        self::assertSame($expectedCause, $this->googleSearchConsoleSourceService->classifyFailure($googleApiFailure));
        self::assertSame($statusCode, $googleApiFailure->httpStatus);
        self::assertLessThanOrEqual(GoogleApiFailure::MAX_MESSAGE_LENGTH, mb_strlen($googleApiFailure->message));
        self::assertSame($expectedCause->isRetryable(), in_array($expectedCause, [SiteInsightsErrorCause::QUOTA_EXCEEDED, SiteInsightsErrorCause::PROVIDER_FAILED], true));
    }

    public function testA2xxAnswerIsNoFailureAndARejectionIsTransport(): void
    {
        $argusApiOperation = (new ReflectionClass(ArgusApiOperation::class))->newInstanceWithoutConstructor();
        $argusApiOperation->responseStatusCode = 200;
        self::assertNull(GoogleApiFailure::fromAnswer((object)['rows' => []], $argusApiOperation));
        self::assertSame(SiteInsightsErrorCause::ANSWER_UNREADABLE, GoogleApiFailure::fromAnswer(null, $argusApiOperation)->getCause());

        $rejectedOperation = (new ReflectionClass(ArgusApiOperation::class))->newInstanceWithoutConstructor();
        $rejectedOperation->rejectionReason = 'RuntimeException: Private Argus transport failed';
        self::assertSame(SiteInsightsErrorCause::TRANSPORT, GoogleApiFailure::fromAnswer(null, $rejectedOperation)->getCause());
    }

    public function testTheSameQuestionIsAskedOnce(): void
    {
        $capturingNetwork = CapturingNetwork::install(GoogleFixtures::response('searchanalytics-totals'));

        $searchReports = $this->googleSearchConsoleSourceService->loadReports(
            $this->getSite(),
            SearchQuestionFactory::createSet(SearchQuestionFactory::create(), SearchQuestionFactory::create())
        );

        self::assertSame(1, $capturingNetwork->countSentRequests());
        self::assertCount(1, $searchReports->getElements());
        self::assertSame(123, $searchReports->first()->performanceRows->first()->metrics->clicks);
    }

    public function testACachedAnswerIsServedUntilItIsOlderThanItsLifetime(): void
    {
        $capturingNetwork = CapturingNetwork::install(GoogleFixtures::response('searchanalytics-queries'), GoogleFixtures::response('searchanalytics-queries'));
        $searchQuestions = SearchQuestionFactory::createSet(SearchQuestionFactory::create(['query', 'device']));

        $this->googleSearchConsoleSourceService->loadReports($this->getSite(), $searchQuestions);
        $cachedReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), $searchQuestions);

        self::assertSame(1, $capturingNetwork->countSentRequests());
        $cachedRows = $cachedReports->first()->performanceRows;
        self::assertTrue($cachedRows->coverage->fromCache);
        self::assertSame(0, $cachedRows->coverage->providerCalls);
        self::assertCount(3, $cachedRows->getElements());

        // the module's own freshness check: an entry older than the question's lifetime is a miss
        $this->googleSearchConsoleSourceService->freshnessCheckTimestamp = time() + 1801;
        $reloadedReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), $searchQuestions);
        self::assertSame(2, $capturingNetwork->countSentRequests());
        self::assertFalse($reloadedReports->first()->performanceRows->coverage->fromCache);
    }

    public function testLifetimesAndLevels(): void
    {
        CapturingNetwork::install(GoogleFixtures::response('searchanalytics-totals'), GoogleFixtures::response('searchanalytics-queries'));
        $totalsQuestion = SearchQuestionFactory::create(aggregationType: 'byProperty', rowLimit: 1);
        $totalsQuestion->dataThroughDate = SearchDays::toDate('2026-11-13');
        $queriesQuestion = SearchQuestionFactory::create(['query'], dataState: 'all');

        $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($totalsQuestion, $queriesQuestion));

        $totalsCacheKey = ArgusCache::getCacheKey($this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($this->getSite(), $totalsQuestion)->cacheKey());
        $queriesCacheKey = ArgusCache::getCacheKey($this->googleSearchConsoleSourceService->buildArgusReportOfQuestion($this->getSite(), $queriesQuestion)->cacheKey());
        self::assertSame(86400, $this->getRedisCache()->ttlsByKey[$totalsCacheKey], 'final days within the last final day');
        self::assertSame(3600, $this->getApcCache()->ttlsByKey[$totalsCacheKey], 'totals also in memory, at most an hour');
        self::assertSame(1800, $this->getRedisCache()->ttlsByKey[$queriesCacheKey], 'dataState all: fresh lifetime');
        self::assertArrayNotHasKey($queriesCacheKey, $this->getApcCache()->valuesByKey, 'row lists in Redis only');
    }

    public function testAnOversizedChunkedAnswerFailsOnlyItsOwnPart(): void
    {
        TestConfig::apply(['budgets' => ['maxResponseBytes' => 20000]]);
        $capturingNetwork = CapturingNetwork::install(
            GoogleFixtures::oversizeChunkedResponse(30000),
            GoogleFixtures::response('searchanalytics-totals'),
            GoogleFixtures::oversizeChunkedResponse(30000),
            GoogleFixtures::response('searchanalytics-totals')
        );
        $oversizedQuestion = SearchQuestionFactory::create(['query'], rowLimit: 2500);
        $siblingQuestion = SearchQuestionFactory::create();

        $searchReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet($oversizedQuestion, $siblingQuestion));

        self::assertSame(4, $capturingNetwork->countSentRequests(), 'the batch, then each part alone');
        $oversizedRows = $searchReports->findReportForQuestion($oversizedQuestion)->performanceRows;
        self::assertSame(SiteInsightsErrorCause::ANSWER_UNREADABLE->value, $oversizedRows->error->cause);
        self::assertSame(2, $oversizedRows->coverage->providerCalls, 'retries count');
        $siblingRows = $searchReports->findReportForQuestion($siblingQuestion)->performanceRows;
        self::assertFalse(isset($siblingRows->error));
        self::assertSame(123, $siblingRows->first()->metrics->clicks);
        self::assertSame(4, $this->sumBudgetCounters(), 'every sent call is counted');
    }

    public function testA401DropsTheTokenAndAsksOnceMore(): void
    {
        $capturingNetwork = CapturingNetwork::install(GoogleFixtures::response('error-401', 401), GoogleFixtures::response('searchanalytics-totals'));

        $searchReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet(SearchQuestionFactory::create()));

        self::assertSame(1, $this->credentialsProviderService->forgottenTokens);
        self::assertSame(2, $capturingNetwork->countSentRequests());
        self::assertFalse(isset($searchReports->first()->performanceRows->error));

        $capturingNetwork->queue(GoogleFixtures::response('error-401', 401), GoogleFixtures::response('error-401', 401));
        $refusedReports = $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet(SearchQuestionFactory::create(['page'])));
        self::assertSame(SiteInsightsErrorCause::TOKEN_REFUSED->value, $refusedReports->first()->performanceRows->error->cause);
        self::assertSame(4, $capturingNetwork->countSentRequests(), 'one retry only');
    }

    public function testTheBudgetRefusesBeforeAnyCall(): void
    {
        TestConfig::apply(['budgets' => ['searchAnalyticsPerMinutePerSite' => 2, 'searchAnalyticsShare' => 0.5]]);
        $capturingNetwork = CapturingNetwork::install();

        $searchReports = $this->googleSearchConsoleSourceService->loadReports(
            $this->getSite(),
            SearchQuestionFactory::createSet(SearchQuestionFactory::create(['query']), SearchQuestionFactory::create(['page']))
        );

        self::assertSame(0, $capturingNetwork->countSentRequests());
        foreach ($searchReports->getElements() as $searchReport) {
            self::assertSame(SiteInsightsErrorCause::BUDGET_REFUSED->value, $searchReport->performanceRows->error->cause);
            self::assertTrue($searchReport->performanceRows->error->retryable);
        }
        self::assertSame(0, $this->sumBudgetCounters(), 'refused calls are not counted');
        self::assertSame([], $this->getRedisCache()->valuesByKey, 'the pending markers were released');
    }

    public function testWithoutTokenNothingIsSent(): void
    {
        $this->credentialsProviderService->accessToken = '';
        $capturingNetwork = CapturingNetwork::install(GoogleFixtures::response('searchanalytics-totals'));

        try {
            $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet(SearchQuestionFactory::create()));
            self::fail('loaded without a token');
        } catch (SiteInsightsProviderUnavailableException $siteInsightsProviderUnavailableException) {
            self::assertSame(SiteInsightsErrorCause::TOKEN_MISSING, $siteInsightsProviderUnavailableException->getCause());
        }
        self::assertSame(0, $capturingNetwork->countSentRequests());
        self::assertSame([], $this->getRedisCache()->valuesByKey, 'the pending marker was released');
    }

    public function testAvailabilityProbe(): void
    {
        $capturingNetwork = CapturingNetwork::install(GoogleFixtures::response('probe-ten-days'));

        $searchDataAvailability = $this->googleSearchConsoleSourceService->getAvailability($this->getSite(), 'web', 'final');

        self::assertSame('2026-11-13', SearchDays::fromDate($searchDataAvailability->dataThroughDate));
        self::assertSame(SearchWindow::DATA_THROUGH_DATE_SOURCE_PROBE, $searchDataAvailability->dataThroughDateSource);
        $probeBody = json_decode((string)$capturingNetwork->getSentRequests()[0]->getBody(), true);
        self::assertSame(['startDate' => '2026-11-06', 'endDate' => '2026-11-16', 'dimensions' => ['date'], 'type' => 'web', 'dataState' => 'final', 'aggregationType' => 'byProperty', 'rowLimit' => 11, 'startRow' => 0], $probeBody);
        $probeCacheKey = array_key_first($this->getApcCache()->valuesByKey);
        self::assertSame(3600, $this->getApcCache()->ttlsByKey[$probeCacheKey], 'the probe lives an hour in memory and Redis');

        $this->googleSearchConsoleSourceService->getAvailability($this->getSite(), 'web', 'final');
        self::assertSame(1, $capturingNetwork->countSentRequests(), 'the probe is cached');
    }

    public function testAvailabilityFallbackAndFreshData(): void
    {
        CapturingNetwork::install(GoogleFixtures::response('probe-empty'), GoogleFixtures::response('searchanalytics-dates-all'));

        $searchDataAvailability = $this->googleSearchConsoleSourceService->getAvailability($this->getSite(), 'web', 'all');

        self::assertSame('2026-11-13', SearchDays::fromDate($searchDataAvailability->dataThroughDate), 'today − 3');
        self::assertSame(SearchWindow::DATA_THROUGH_DATE_SOURCE_FALLBACK, $searchDataAvailability->dataThroughDateSource);
        self::assertSame('2026-11-15', SearchDays::fromDate($searchDataAvailability->firstIncompleteDate));
    }

    public function testEarliestDayAndToday(): void
    {
        self::assertSame('2026-11-16', $this->googleSearchConsoleSourceService->getToday());
        self::assertSame('2025-07-19', $this->googleSearchConsoleSourceService->getEarliestDay());
        self::assertSame('America/Los_Angeles', $this->googleSearchConsoleSourceService->getCapabilities()->dayTimeZone);
    }

    public function testAnUnknownDimensionIsRefused(): void
    {
        CapturingNetwork::install(new Response(200, [], '{}'));

        $this->expectException(\DDD\Infrastructure\Exceptions\BadRequestException::class);
        $this->googleSearchConsoleSourceService->loadReports($this->getSite(), SearchQuestionFactory::createSet(SearchQuestionFactory::create(['hour'])));
    }

    protected function sumBudgetCounters(): int
    {
        return array_sum($this->getRedisCache()->countersByKey);
    }
}
