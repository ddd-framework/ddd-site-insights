<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\AnsweringNetwork;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticSearchAnalytics;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestConfig;
use GuzzleHttp\Psr7\Response;

/**
 * Coverage flags of questions that cannot have more rows (totals, a series of every day of its window), and how a
 * filtered series is filled: a day without a row is filled by the module; a row the provider sends is reported as sent.
 */
class SearchCoverageAndSeriesTest extends SiteInsightsTestCase
{
    public const string QUERY = 'synthetic widget 004';

    protected SyntheticSearchAnalytics $syntheticSearchAnalytics;

    /** @var array[] rows the filtered date series answers with: [day, clicks, impressions, position] */
    protected array $filteredSeriesRows = [];

    protected function setUp(): void
    {
        parent::setUp();
        $administratorAccount = TestAccounts::admin();
        AuthService::instance()->setAccount($administratorAccount);
        $this->syntheticSearchAnalytics = new SyntheticSearchAnalytics();
        AnsweringNetwork::install(function (array $requestBody): Response {
            if (($requestBody['dimensions'] ?? []) === ['date'] && isset($requestBody['dimensionFilterGroups'])) {
                return SyntheticSearchAnalytics::respond(array_map(
                    static fn (array $seriesRow): array => ['keys' => [$seriesRow[0]], 'clicks' => $seriesRow[1], 'impressions' => $seriesRow[2], 'ctr' => 0, 'position' => $seriesRow[3]],
                    $this->filteredSeriesRows
                ));
            }
            return $this->syntheticSearchAnalytics->answer($requestBody);
        });
    }

    public function testTotalsAndAFullSeriesNeverReachALimitWhileATopListDoes(): void
    {
        $overview = $this->getSearchPerformanceService()->getOverview(TestConfig::SITE_KEY, new SearchWindowRequest());

        foreach (['totals' => $overview->totals->coverage, 'series' => $overview->series->coverage] as $partName => $searchRowsCoverage) {
            self::assertFalse($searchRowsCoverage->limitReached, $partName);
            self::assertTrue($searchRowsCoverage->providerRowsExhausted, $partName);
        }
        self::assertSame(1, $overview->totals->coverage->fetchedRows);
        self::assertSame(28, $overview->series->coverage->fetchedRows, 'a row for every day of the window');
        self::assertTrue($overview->topQueries->coverage->limitReached, '30 rows exist, 25 asked');
        self::assertFalse($overview->topQueries->coverage->providerRowsExhausted);
    }

    public function testAFilteredSeriesFillsTheDaysWithoutARow(): void
    {
        $this->filteredSeriesRows = [['2026-11-02', 0, 1, 7.0]];

        $searchQueryDetail = $this->getSearchPerformanceService()->getQueryDetail(TestConfig::SITE_KEY, self::QUERY, new SearchWindowRequest());

        $seriesRows = array_values($searchQueryDetail->series->getElements());
        self::assertCount(28, $seriesRows);
        $reportedRows = array_values(array_filter($seriesRows, static fn ($seriesRow) => !$seriesRow->isFilledDay));
        self::assertCount(1, $reportedRows, 'the one row the provider sent');
        self::assertSame('2026-11-02', SearchDays::fromDate($reportedRows[0]->date));
        self::assertTrue($reportedRows[0]->metrics->hasData);
        foreach ($seriesRows as $seriesRow) {
            if ($seriesRow->isFilledDay) {
                self::assertFalse($seriesRow->metrics->hasData);
                self::assertSame(0, $seriesRow->metrics->impressions);
            }
        }
        self::assertFalse($searchQueryDetail->series->coverage->limitReached);
    }

    public function testExplicitZeroRowsOfTheProviderAreReportedAsSent(): void
    {
        // the provider may answer a filtered date series with a row for days without impressions: the module neither
        // drops nor fills such a row, it reports it (isFilledDay false, hasData true, 0 impressions)
        $this->filteredSeriesRows = [['2026-11-02', 0, 1, 7.0], ['2026-11-03', 0, 0, 0.0]];

        $searchQueryDetail = $this->getSearchPerformanceService()->getQueryDetail(TestConfig::SITE_KEY, self::QUERY, new SearchWindowRequest());

        $zeroRow = null;
        foreach ($searchQueryDetail->series->getElements() as $seriesRow) {
            if (SearchDays::fromDate($seriesRow->date) === '2026-11-03') {
                $zeroRow = $seriesRow;
            }
        }
        self::assertFalse($zeroRow->isFilledDay);
        self::assertTrue($zeroRow->metrics->hasData);
        self::assertSame(0, $zeroRow->metrics->impressions);
    }

    protected function getSearchPerformanceService(): SearchPerformanceService
    {
        return DDDService::instance()->getService(SearchPerformanceService::class);
    }
}
