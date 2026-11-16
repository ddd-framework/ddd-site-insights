<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Services;

use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchDataAvailability;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Services\Service;

/**
 * Window arithmetic: presets and explicit days become an inclusive window that ends on the last final day (`final`)
 * or today (`all`) in the provider's day zone, raised to the earliest day the provider keeps; comparisons are the
 * previous period or the same days 52 weeks earlier, shortened to the retention or refused.
 */
class SearchWindowsService extends Service
{
    /** @var int Most days of an explicit window (16 months) */
    public const int MAX_WINDOW_DAYS = 486;

    /** @var int Days a previous-year comparison goes back (52 weeks: weekdays aligned) */
    public const int PREVIOUS_YEAR_DAYS = 364;

    /** @var string[] */
    public const array COMPARE_VALUES = [
        SearchWindowRequest::COMPARE_NONE,
        SearchComparisonWindow::KIND_PREVIOUS_PERIOD,
        SearchComparisonWindow::KIND_PREVIOUS_YEAR,
    ];

    /**
     * @param InsightsSite $insightsSite
     * @param SearchWindowRequest $searchWindowRequest
     * @return SearchWindow the window, with the last final day read from the provider (availability probe)
     * @throws BadRequestException when the request is not a valid window
     */
    public function buildWindow(InsightsSite $insightsSite, SearchWindowRequest $searchWindowRequest): SearchWindow
    {
        $this->validateWindowRequest($searchWindowRequest);
        /** @var GoogleSearchConsoleSourceService $googleSearchConsoleSourceService */
        $googleSearchConsoleSourceService = DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);
        $searchDataAvailability = $googleSearchConsoleSourceService->getAvailability(
            $insightsSite,
            $searchWindowRequest->searchType,
            $searchWindowRequest->dataState
        );
        return $this->buildWindowForDays(
            $searchWindowRequest,
            $googleSearchConsoleSourceService->getToday(),
            $googleSearchConsoleSourceService->getEarliestDay(),
            $searchDataAvailability,
            GoogleSearchConsoleSourceService::DAY_TIME_ZONE
        );
    }

    /**
     * The window for known days (no provider call).
     *
     * @param SearchWindowRequest $searchWindowRequest
     * @param string $today today in the provider's zone
     * @param string $earliestDay the earliest day the provider keeps
     * @param SearchDataAvailability $searchDataAvailability
     * @param string $dayTimeZone
     * @return SearchWindow
     * @throws BadRequestException
     */
    public function buildWindowForDays(
        SearchWindowRequest $searchWindowRequest,
        string $today,
        string $earliestDay,
        SearchDataAvailability $searchDataAvailability,
        string $dayTimeZone
    ): SearchWindow {
        $this->validateWindowRequest($searchWindowRequest);
        $dataThroughDay = SearchDays::fromDate($searchDataAvailability->dataThroughDate);
        $lastAllowedDay = $searchWindowRequest->dataState === SearchWindowRequest::DATA_STATE_FINAL ? $dataThroughDay : $today;
        $searchWindow = new SearchWindow();
        $hasExplicitDays = isset($searchWindowRequest->from) || isset($searchWindowRequest->to);
        if ($hasExplicitDays) {
            if (!isset($searchWindowRequest->from, $searchWindowRequest->to)) {
                throw new BadRequestException('An explicit window needs both from and to');
            }
            $fromDay = SearchDays::fromDate($searchWindowRequest->from);
            $toDay = SearchDays::fromDate($searchWindowRequest->to);
            if (strcmp($fromDay, $toDay) > 0) {
                throw new BadRequestException('from must be on or before to');
            }
            if (strcmp($toDay, $lastAllowedDay) > 0) {
                throw new BadRequestException('to must be on or before ' . $lastAllowedDay . ' (the last day with ' . $searchWindowRequest->dataState . ' data)');
            }
            if (SearchDays::daysInclusive($fromDay, $toDay) > self::MAX_WINDOW_DAYS) {
                throw new BadRequestException('A window has at most ' . self::MAX_WINDOW_DAYS . ' days');
            }
        } else {
            $toDay = $lastAllowedDay;
            $preset = $searchWindowRequest->preset;
            if (isset(SearchWindowRequest::DAY_PRESETS[$preset])) {
                $fromDay = SearchDays::addDays($toDay, -(SearchWindowRequest::DAY_PRESETS[$preset] - 1));
            } elseif (isset(SearchWindowRequest::MONTH_PRESETS[$preset])) {
                $fromDay = SearchDays::addDays(SearchDays::subtractMonthsClamped($toDay, SearchWindowRequest::MONTH_PRESETS[$preset]), 1);
            } else {
                throw new BadRequestException('Unknown preset "' . mb_substr($preset, 0, 10) . '"');
            }
            $searchWindow->preset = $preset;
        }
        if (strcmp($fromDay, $earliestDay) < 0) {
            $fromDay = $earliestDay;
            $searchWindow->windowClamped = true;
        }
        if (strcmp($fromDay, $toDay) > 0) {
            throw new BadRequestException('The window lies outside the days the provider keeps (from ' . $earliestDay . ')');
        }
        $searchWindow->from = SearchDays::toDate($fromDay);
        $searchWindow->to = SearchDays::toDate($toDay);
        $searchWindow->days = SearchDays::daysInclusive($fromDay, $toDay);
        $searchWindow->dayTimeZone = $dayTimeZone;
        $searchWindow->dataState = $searchWindowRequest->dataState;
        $searchWindow->searchType = $searchWindowRequest->searchType;
        $searchWindow->dataThroughDate = SearchDays::toDate($dataThroughDay);
        $searchWindow->dataThroughDateSource = $searchDataAvailability->dataThroughDateSource;
        if (isset($searchDataAvailability->firstIncompleteDate)) {
            $searchWindow->firstIncompleteDate = SearchDays::toDate(SearchDays::fromDate($searchDataAvailability->firstIncompleteDate));
        }
        return $searchWindow;
    }

    /**
     * @param SearchWindow $searchWindow
     * @param string $compare none, previousPeriod or previousYear
     * @param string|null $earliestDay the earliest day the provider keeps (default: the provider's)
     * @return SearchComparisonWindow|null null for none; a window with `comparisonUnavailableReason` when refused
     * @throws BadRequestException for an unknown comparison
     */
    public function buildComparisonWindow(SearchWindow $searchWindow, string $compare, ?string $earliestDay = null): ?SearchComparisonWindow
    {
        if (!in_array($compare, self::COMPARE_VALUES, true)) {
            throw new BadRequestException('compare must be one of ' . implode(', ', self::COMPARE_VALUES));
        }
        if ($compare === SearchWindowRequest::COMPARE_NONE) {
            return null;
        }
        if ($earliestDay === null) {
            /** @var GoogleSearchConsoleSourceService $googleSearchConsoleSourceService */
            $googleSearchConsoleSourceService = DDDService::instance()->getService(GoogleSearchConsoleSourceService::class);
            $earliestDay = $googleSearchConsoleSourceService->getEarliestDay();
        }
        $fromDay = SearchDays::fromDate($searchWindow->from);
        $toDay = SearchDays::fromDate($searchWindow->to);
        if ($compare === SearchComparisonWindow::KIND_PREVIOUS_PERIOD) {
            $previousToDay = SearchDays::addDays($fromDay, -1);
            $previousFromDay = SearchDays::addDays($previousToDay, -($searchWindow->days - 1));
        } else {
            $previousFromDay = SearchDays::addDays($fromDay, -self::PREVIOUS_YEAR_DAYS);
            $previousToDay = SearchDays::addDays($toDay, -self::PREVIOUS_YEAR_DAYS);
        }
        $searchComparisonWindow = new SearchComparisonWindow();
        $searchComparisonWindow->kind = $compare;
        $searchComparisonWindow->dayTimeZone = $searchWindow->dayTimeZone;
        $searchComparisonWindow->dataState = $searchWindow->dataState;
        $searchComparisonWindow->searchType = $searchWindow->searchType;
        if (isset($searchWindow->dataThroughDate)) {
            $searchComparisonWindow->dataThroughDate = SearchDays::toDate(SearchDays::fromDate($searchWindow->dataThroughDate));
            $searchComparisonWindow->dataThroughDateSource = $searchWindow->dataThroughDateSource ?? SearchWindow::DATA_THROUGH_DATE_SOURCE_FALLBACK;
        }
        if (strcmp($previousToDay, $earliestDay) < 0) {
            $searchComparisonWindow->comparisonUnavailableReason = SearchComparisonWindow::UNAVAILABLE_OUTSIDE_RETENTION;
            return $searchComparisonWindow;
        }
        if (strcmp($previousFromDay, $earliestDay) < 0) {
            $previousFromDay = $earliestDay;
            $searchComparisonWindow->comparisonIsPartial = true;
            $searchComparisonWindow->windowClamped = true;
        }
        $comparisonDays = SearchDays::daysInclusive($previousFromDay, $previousToDay);
        if ($searchComparisonWindow->comparisonIsPartial) {
            $comparisonLimits = (array)$this->getSiteInsightsConfigService()->getBudgetSetting('comparison');
            if ($comparisonDays < (int)$comparisonLimits['minDays'] || $comparisonDays / $searchWindow->days < (float)$comparisonLimits['minShare']) {
                $searchComparisonWindow->comparisonUnavailableReason = SearchComparisonWindow::UNAVAILABLE_TOO_SHORT_INSIDE_RETENTION;
                return $searchComparisonWindow;
            }
        }
        $searchComparisonWindow->from = SearchDays::toDate($previousFromDay);
        $searchComparisonWindow->to = SearchDays::toDate($previousToDay);
        $searchComparisonWindow->days = $comparisonDays;
        return $searchComparisonWindow;
    }

    /**
     * @param SearchWindowRequest $searchWindowRequest
     * @return void
     * @throws BadRequestException
     */
    protected function validateWindowRequest(SearchWindowRequest $searchWindowRequest): void
    {
        if (!in_array($searchWindowRequest->dataState, GoogleSearchConsoleSourceService::DATA_STATES, true)) {
            throw new BadRequestException('dataState must be final or all');
        }
        if (!in_array($searchWindowRequest->searchType, GoogleSearchConsoleSourceService::SEARCH_TYPES, true)) {
            throw new BadRequestException('searchType must be one of ' . implode(', ', GoogleSearchConsoleSourceService::SEARCH_TYPES));
        }
        if (!in_array($searchWindowRequest->compare, self::COMPARE_VALUES, true)) {
            throw new BadRequestException('compare must be one of ' . implode(', ', self::COMPARE_VALUES));
        }
    }

    protected function getSiteInsightsConfigService(): SiteInsightsConfigService
    {
        return DDDService::instance()->getService(SiteInsightsConfigService::class);
    }
}
