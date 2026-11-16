<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Services\GoogleSearchConsoleSourceService;
use DDD\Domain\SiteInsights\Services\SearchWindowsService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;

/**
 * The example table of the window arithmetic: today (Pacific Time) 2026-11-16, last final day 2026-11-13, earliest
 * day 2025-07-19.
 */
class SearchWindowsServiceTest extends SiteInsightsTestCase
{
    protected const string TODAY = '2026-11-16';
    protected const string DATA_THROUGH_DAY = '2026-11-13';
    protected const string EARLIEST_DAY = '2025-07-19';

    protected string $defaultTimeZoneBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultTimeZoneBefore = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimeZoneBefore);
        parent::tearDown();
    }

    /** @return array[] preset or [from, to], compare, expected window [from, to, days], expected comparison */
    public static function provideExampleTable(): array
    {
        return [
            '7d previousPeriod' => ['7d', null, 'previousPeriod', ['2026-11-07', '2026-11-13', 7], ['2026-10-31', '2026-11-06', false, null]],
            '28d previousPeriod' => ['28d', null, 'previousPeriod', ['2026-10-17', '2026-11-13', 28], ['2026-09-19', '2026-10-16', false, null]],
            '28d previousYear' => ['28d', null, 'previousYear', ['2026-10-17', '2026-11-13', 28], ['2025-10-18', '2025-11-14', false, null]],
            '90d previousYear' => ['90d', null, 'previousYear', ['2026-08-16', '2026-11-13', 90], ['2025-08-17', '2025-11-14', false, null]],
            '6m previousYear' => ['6m', null, 'previousYear', ['2026-05-14', '2026-11-13', 184], ['2025-07-19', '2025-11-14', true, null]],
            '12m previousPeriod' => ['12m', null, 'previousPeriod', ['2025-11-14', '2026-11-13', 365], ['2025-07-19', '2025-11-13', true, null]],
            '16m previousPeriod' => ['16m', null, 'previousPeriod', ['2025-07-19', '2026-11-13', 483], [null, null, false, 'outsideRetention']],
            '16m previousYear' => ['16m', null, 'previousYear', ['2025-07-19', '2026-11-13', 483], [null, null, true, 'tooShortInsideRetention']],
            '6m on to 2026-08-31' => ['6m', [null, '2026-08-31'], 'none', ['2026-03-01', '2026-08-31', 184], null],
        ];
    }

    /** @dataProvider provideExampleTable */
    public function testExampleTable(string $preset, ?array $explicitDays, string $compare, array $expectedWindow, ?array $expectedComparison): void
    {
        foreach (['Pacific/Auckland', 'America/Los_Angeles', 'UTC'] as $defaultTimeZone) {
            date_default_timezone_set($defaultTimeZone);
            $searchWindowRequest = new SearchWindowRequest();
            $searchWindowRequest->preset = $preset;
            $searchWindowRequest->compare = $compare;
            $dataThroughDay = self::DATA_THROUGH_DAY;
            if ($explicitDays !== null) {
                // a preset ending on another day: the last final day is that day
                $dataThroughDay = $explicitDays[1];
            }
            $searchWindow = $this->getSearchWindowsService()->buildWindowForDays(
                $searchWindowRequest,
                self::TODAY,
                $explicitDays !== null ? '2020-01-01' : self::EARLIEST_DAY,
                $this->createAvailability($dataThroughDay),
                GoogleSearchConsoleSourceService::DAY_TIME_ZONE
            );
            self::assertSame($expectedWindow[0], SearchDays::fromDate($searchWindow->from), $defaultTimeZone);
            self::assertSame($expectedWindow[1], SearchDays::fromDate($searchWindow->to), $defaultTimeZone);
            self::assertSame($expectedWindow[2], $searchWindow->days, $defaultTimeZone);
            self::assertSame($preset === '16m', $searchWindow->windowClamped);

            $searchComparisonWindow = $this->getSearchWindowsService()->buildComparisonWindow($searchWindow, $compare, self::EARLIEST_DAY);
            if ($expectedComparison === null) {
                self::assertNull($searchComparisonWindow);
                continue;
            }
            [$expectedFrom, $expectedTo, $expectedPartial, $expectedUnavailableReason] = $expectedComparison;
            self::assertSame($expectedUnavailableReason, $searchComparisonWindow->comparisonUnavailableReason ?? null, $defaultTimeZone);
            self::assertSame($expectedPartial, $searchComparisonWindow->comparisonIsPartial);
            if ($expectedFrom !== null) {
                self::assertSame($expectedFrom, SearchDays::fromDate($searchComparisonWindow->from), $defaultTimeZone);
                self::assertSame($expectedTo, SearchDays::fromDate($searchComparisonWindow->to), $defaultTimeZone);
            } else {
                self::assertFalse(isset($searchComparisonWindow->from));
            }
        }
    }

    public function testSixteenMonthsStartFiveDaysBeforeTheEarliestDayWithoutClamping(): void
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->preset = '16m';
        $searchWindow = $this->getSearchWindowsService()->buildWindowForDays(
            $searchWindowRequest,
            self::TODAY,
            '2020-01-01',
            $this->createAvailability(self::DATA_THROUGH_DAY),
            GoogleSearchConsoleSourceService::DAY_TIME_ZONE
        );

        self::assertSame('2025-07-14', SearchDays::fromDate($searchWindow->from));
    }

    public function testSevenDaysOverALeapDayAndItsPreviousYear(): void
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->preset = '7d';
        $searchWindow = $this->getSearchWindowsService()->buildWindowForDays(
            $searchWindowRequest,
            '2028-03-04',
            '2026-11-01',
            $this->createAvailability('2028-03-01'),
            GoogleSearchConsoleSourceService::DAY_TIME_ZONE
        );
        $searchComparisonWindow = $this->getSearchWindowsService()->buildComparisonWindow($searchWindow, SearchComparisonWindow::KIND_PREVIOUS_YEAR, '2026-11-01');

        self::assertSame('2028-02-24', SearchDays::fromDate($searchWindow->from));
        self::assertSame(7, $searchWindow->days);
        // 52 weeks back: 2027-02-25 (Thursday) – 2027-03-03, seven days like the window
        self::assertSame('2027-02-25', SearchDays::fromDate($searchComparisonWindow->from));
        self::assertSame('2027-03-03', SearchDays::fromDate($searchComparisonWindow->to));
        self::assertSame(7, $searchComparisonWindow->days);
        self::assertSame($searchWindow->from->format('N'), $searchComparisonWindow->from->format('N'));
    }

    public function testExplicitDaysAreValidatedAndRaisedToTheEarliestDay(): void
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->from = SearchDays::toDate('2025-07-01');
        $searchWindowRequest->to = SearchDays::toDate('2025-08-31');
        $searchWindow = $this->getSearchWindowsService()->buildWindowForDays($searchWindowRequest, self::TODAY, self::EARLIEST_DAY, $this->createAvailability(self::DATA_THROUGH_DAY), GoogleSearchConsoleSourceService::DAY_TIME_ZONE);

        self::assertSame(self::EARLIEST_DAY, SearchDays::fromDate($searchWindow->from));
        self::assertTrue($searchWindow->windowClamped);
        self::assertFalse(isset($searchWindow->preset));
    }

    /** @return array[] */
    public static function provideInvalidWindows(): array
    {
        return [
            'to after the last final day' => ['2026-11-01', '2026-11-14', 'final'],
            'from after to' => ['2026-11-10', '2026-11-01', 'final'],
            'more than 486 days' => ['2025-01-01', '2026-11-13', 'final'],
            'all beyond today' => ['2026-11-01', '2026-11-17', 'all'],
        ];
    }

    /** @dataProvider provideInvalidWindows */
    public function testInvalidExplicitWindowsAreRefused(string $fromDay, string $toDay, string $dataState): void
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->from = SearchDays::toDate($fromDay);
        $searchWindowRequest->to = SearchDays::toDate($toDay);
        $searchWindowRequest->dataState = $dataState;

        $this->expectException(BadRequestException::class);
        $this->getSearchWindowsService()->buildWindowForDays($searchWindowRequest, self::TODAY, '2024-01-01', $this->createAvailability(self::DATA_THROUGH_DAY), GoogleSearchConsoleSourceService::DAY_TIME_ZONE);
    }

    public function testDataStateAllEndsOnToday(): void
    {
        $searchWindowRequest = new SearchWindowRequest();
        $searchWindowRequest->preset = '7d';
        $searchWindowRequest->dataState = SearchWindowRequest::DATA_STATE_ALL;
        $searchWindow = $this->getSearchWindowsService()->buildWindowForDays($searchWindowRequest, self::TODAY, self::EARLIEST_DAY, $this->createAvailability(self::DATA_THROUGH_DAY), GoogleSearchConsoleSourceService::DAY_TIME_ZONE);

        self::assertSame(self::TODAY, SearchDays::fromDate($searchWindow->to));
        self::assertSame(self::DATA_THROUGH_DAY, SearchDays::fromDate($searchWindow->dataThroughDate));
    }

    protected function createAvailability(string $dataThroughDay): SearchDataAvailability
    {
        $searchDataAvailability = new SearchDataAvailability();
        $searchDataAvailability->searchType = 'web';
        $searchDataAvailability->dataState = 'final';
        $searchDataAvailability->dataThroughDate = SearchDays::toDate($dataThroughDay);
        $searchDataAvailability->dataThroughDateSource = SearchWindow::DATA_THROUGH_DATE_SOURCE_PROBE;
        return $searchDataAvailability;
    }

    protected function getSearchWindowsService(): SearchWindowsService
    {
        return DDDService::instance()->getService(SearchWindowsService::class);
    }
}
