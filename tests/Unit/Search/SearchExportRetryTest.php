<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Errors\SiteInsightsErrorCause;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchExportSummary;
use DDD\Domain\SiteInsights\Entities\Search\SearchTransportFailure;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SteppingClockSearchPerformanceService;
use DDD\Tests\SiteInsights\Support\SyntheticSearchAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestConfig;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Throwable;

/** The export asks a failed page again, names how it failed, and continues after a day */
class SearchExportRetryTest extends SiteInsightsTestCase
{
    public const string FAILING_DAY = '2026-11-11';

    protected SyntheticSearchAnalytics $syntheticSearchAnalytics;

    protected SteppingClockSearchPerformanceService $searchPerformanceService;

    /** @var int[] calls per start day */
    protected array $callsByStartDay = [];

    /** @var int How many calls of FAILING_DAY fail before it answers */
    protected int $failuresOfFailingDay = 0;

    /** @var callable fn(): Response|Throwable the failure FAILING_DAY gets */
    protected $failingAnswer;

    protected function setUp(): void
    {
        parent::setUp();
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 50]]);
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $this->syntheticSearchAnalytics = new SyntheticSearchAnalytics();
        $this->searchPerformanceService = new SteppingClockSearchPerformanceService();
        $this->setService(SearchPerformanceService::class, $this->searchPerformanceService);
        $this->failingAnswer = static fn (): Throwable => new ConnectException('synthetic connection refused', new Request('POST', 'https://www.googleapis.com/'));
        AnsweringNetwork::install(function (array $requestBody): Response|Throwable {
            if (SyntheticSearchAnalytics::isProbe($requestBody)) {
                return $this->syntheticSearchAnalytics->answer($requestBody);
            }
            $startDay = (string)$requestBody['startDate'];
            $this->callsByStartDay[$startDay] = ($this->callsByStartDay[$startDay] ?? 0) + 1;
            if ($startDay === self::FAILING_DAY && $this->callsByStartDay[$startDay] <= $this->failuresOfFailingDay) {
                return ($this->failingAnswer)();
            }
            return $this->syntheticSearchAnalytics->answer($requestBody);
        });
    }

    public function testATransientTransportFailureIsAskedAgainAndTheExportGoesOn(): void
    {
        $this->failuresOfFailingDay = 1;

        $searchExportSummary = $this->export();

        self::assertFalse(isset($searchExportSummary->error));
        self::assertSame('2026-11-12', SearchDays::fromDate($searchExportSummary->lastFinishedDay));
        self::assertSame(90, $searchExportSummary->rowsWritten, 'three days, every row once');
        self::assertSame(1, $searchExportSummary->retries);
        self::assertSame(4, $searchExportSummary->providerCalls, 'the retry counts as a call');
        self::assertSame(1, $searchExportSummary->days->getElements()[1]->retries);
        self::assertSame([1000], $this->searchPerformanceService->waitedMilliseconds);
    }

    public function testTheExportEndsAfterTheConfiguredRetriesWithTheTransportDetail(): void
    {
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 50, 'exportRetries' => 2, 'exportRetryBackoffMilliseconds' => 300]]);
        $this->failuresOfFailingDay = 10;

        $searchExportSummary = $this->export();

        self::assertSame('2026-11-10', SearchDays::fromDate($searchExportSummary->lastFinishedDay));
        self::assertFalse($searchExportSummary->limitReached);
        self::assertSame(3, $this->callsByStartDay[self::FAILING_DAY], 'once and two retries');
        self::assertSame([300, 600], $this->searchPerformanceService->waitedMilliseconds);
        $searchPartError = $searchExportSummary->error;
        self::assertSame(SiteInsightsErrorCause::TRANSPORT->value, $searchPartError->cause);
        self::assertTrue($searchPartError->retryable);
        self::assertSame(3, $searchPartError->attempts);
        self::assertFalse(isset($searchPartError->providerStatus), 'no answer, no status');
        self::assertSame(SearchTransportFailure::KIND_ENDED_BEFORE_TIMEOUT, $searchPartError->transportFailure->kind);
        self::assertSame(50, $searchPartError->transportFailure->elapsedMilliseconds);
        self::assertSame(25, $searchPartError->transportFailure->timeoutSeconds);
        self::assertSame($searchPartError, $searchExportSummary->days->getElements()[1]->error);

        $serializedSummary = json_decode((string)json_encode($searchExportSummary->toObject(cached: false, forPersistence: false)), true);
        self::assertSame(['kind' => 'endedBeforeTimeout', 'elapsedMilliseconds' => 50, 'timeoutSeconds' => 25], array_intersect_key(
            $serializedSummary['error']['transportFailure'],
            ['kind' => true, 'elapsedMilliseconds' => true, 'timeoutSeconds' => true]
        ));
    }

    public function testACallThatRunsIntoItsTimeoutIsNamedSo(): void
    {
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 50, 'exportRetries' => 0]]);
        $this->searchPerformanceService->millisecondsPerReading = 25000;
        $this->failuresOfFailingDay = 1;

        $searchExportSummary = $this->export();

        self::assertSame(SearchTransportFailure::KIND_TIMEOUT, $searchExportSummary->error->transportFailure->kind);
        self::assertSame(1, $searchExportSummary->error->attempts);
        self::assertSame([], $this->searchPerformanceService->waitedMilliseconds, 'no retry configured');
    }

    public function testANonRetryableFailureEndsAtOnce(): void
    {
        $this->failuresOfFailingDay = 10;
        $this->failingAnswer = static fn (): Response => SyntheticSearchAnalytics::respond([])->withStatus(400);

        $searchExportSummary = $this->export();

        self::assertSame(1, $this->callsByStartDay[self::FAILING_DAY]);
        self::assertSame(SiteInsightsErrorCause::INVALID_REQUEST->value, $searchExportSummary->error->cause);
        self::assertSame(400, $searchExportSummary->error->providerStatus);
        self::assertFalse(isset($searchExportSummary->error->transportFailure));
        self::assertSame(0, $searchExportSummary->retries);
    }

    public function testRetriesStopAtTheCallBudgetAndKeepTheError(): void
    {
        TestConfig::apply(['budgets' => ['maxRowsPerCall' => 50, 'exportRetries' => 5, 'callsPerOperation' => ['export' => 3]]]);
        $this->failuresOfFailingDay = 10;

        $searchExportSummary = $this->export();

        self::assertSame(3, $searchExportSummary->providerCalls);
        self::assertTrue($searchExportSummary->limitReached);
        self::assertSame(SiteInsightsErrorCause::TRANSPORT->value, $searchExportSummary->error->cause);
        self::assertSame(2, $searchExportSummary->error->attempts);
    }

    public function testResumeAfterContinuesWithTheDayAfter(): void
    {
        $searchExportSummary = $this->export('2026-11-10');

        self::assertSame('2026-11-10', SearchDays::fromDate($searchExportSummary->resumedAfter));
        self::assertSame('2026-11-10', SearchDays::fromDate($searchExportSummary->window->from), 'the window stays');
        self::assertSame(['2026-11-11', '2026-11-12'], array_map(static fn ($searchExportDay) => SearchDays::fromDate($searchExportDay->from), $searchExportSummary->days->getElements()));
        self::assertArrayNotHasKey('2026-11-10', $this->callsByStartDay);
        self::assertSame(60, $searchExportSummary->rowsWritten);
        self::assertSame('2026-11-12', SearchDays::fromDate($searchExportSummary->lastFinishedDay));

        foreach (['2026-11-09', '2026-11-12', '2026-11-20'] as $dayOutsideTheWindow) {
            try {
                $this->export($dayOutsideTheWindow);
                self::fail($dayOutsideTheWindow . ' must be refused');
            } catch (BadRequestException $badRequestException) {
                self::assertStringContainsString('resumeAfter', $badRequestException->getMessage());
            }
        }
    }

    protected function export(?string $resumeAfterDay = null): SearchExportSummary
    {
        $searchExportRequest = new SearchExportRequest();
        $searchExportRequest->from = SearchDays::toDate('2026-11-10');
        $searchExportRequest->to = SearchDays::toDate('2026-11-12');
        if ($resumeAfterDay !== null) {
            $searchExportRequest->resumeAfter = SearchDays::toDate($resumeAfterDay);
        }
        return $this->searchPerformanceService->export(TestConfig::SITE_KEY, $searchExportRequest, static fn () => null);
    }
}
