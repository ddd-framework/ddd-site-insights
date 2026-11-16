<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Traits;

use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchOverviewGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchPageDetailGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchPageDetailRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchPageGroupsGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchQueryDetailGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchQueryDetailRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchRowsGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchRowsRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSearchWindowRequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;

/**
 * The search performance endpoints of an admin API. All are reads and GET: they count against the per-minute Search
 * Analytics budget only, their answers are cached (a final window a day, a fresh one 30 minutes), and none consumes a
 * daily quota. An application mounts them with one controller of its own, which sets the path prefix, the tag and the
 * access:
 *
 *     #[Route('/siteInsights')]
 *     #[Tag(group: 'Website', name: 'SiteInsights', description: 'Search Console insights')]
 *     class AdminSiteInsightsSearchController extends AdminController
 *     {
 *         use SiteInsightsSearchControllerTrait;
 *     }
 *
 * Every action asks SiteInsightsAccessPolicyService first. Each payload equals the JSON of the matching
 * `app:site-insights:*` command.
 */
trait SiteInsightsSearchControllerTrait
{
    use SiteInsightsViewerTrait;

    /**
     * Totals with comparison, the series by day, top queries, top pages, countries and devices of one window, each
     * part with its coverage or error.
     *
     * @param SiteInsightsSearchWindowRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchOverviewGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/overview')]
    #[Summary('SiteInsights Search Overview')]
    public function getSiteInsightsSearchOverview(
        SiteInsightsSearchWindowRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchOverviewGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSearchOverviewGetResponseDto();
        $responseDto->searchPerformanceOverview = $searchPerformanceService->getOverview($requestDto->siteKey, $requestDto->buildSearchWindowRequest());
        return $responseDto;
    }

    /**
     * Rows grouped by query, with the comparison window's counterpart per row when `compare` is set.
     *
     * @param SiteInsightsSearchRowsRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchRowsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/queries')]
    #[Summary('SiteInsights Search Queries')]
    public function getSiteInsightsSearchQueries(
        SiteInsightsSearchRowsRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchRowsGetResponseDto {
        return $this->buildSiteInsightsSearchRowsResponse($requestDto, $searchPerformanceService, SearchListRequest::DIMENSION_QUERY);
    }

    /**
     * Rows grouped by page (page aggregation), with the comparison window's counterpart per row when `compare` is set.
     *
     * @param SiteInsightsSearchRowsRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchRowsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/pages')]
    #[Summary('SiteInsights Search Pages')]
    public function getSiteInsightsSearchPages(
        SiteInsightsSearchRowsRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchRowsGetResponseDto {
        return $this->buildSiteInsightsSearchRowsResponse($requestDto, $searchPerformanceService, SearchListRequest::DIMENSION_PAGE);
    }

    /**
     * Rows grouped by country, with the comparison window's counterpart per row when `compare` is set.
     *
     * @param SiteInsightsSearchRowsRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchRowsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/countries')]
    #[Summary('SiteInsights Search Countries')]
    public function getSiteInsightsSearchCountries(
        SiteInsightsSearchRowsRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchRowsGetResponseDto {
        return $this->buildSiteInsightsSearchRowsResponse($requestDto, $searchPerformanceService, SearchListRequest::DIMENSION_COUNTRY);
    }

    /**
     * Rows grouped by device, with the comparison window's counterpart per row when `compare` is set.
     *
     * @param SiteInsightsSearchRowsRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchRowsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/devices')]
    #[Summary('SiteInsights Search Devices')]
    public function getSiteInsightsSearchDevices(
        SiteInsightsSearchRowsRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchRowsGetResponseDto {
        return $this->buildSiteInsightsSearchRowsResponse($requestDto, $searchPerformanceService, SearchListRequest::DIMENSION_DEVICE);
    }

    /**
     * One aggregate per configured page group, with comparison. Groups may overlap and are never summed.
     *
     * @param SiteInsightsSearchWindowRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchPageGroupsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/pageGroups')]
    #[Summary('SiteInsights Search Page Groups')]
    public function getSiteInsightsSearchPageGroups(
        SiteInsightsSearchWindowRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchPageGroupsGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSearchPageGroupsGetResponseDto();
        $responseDto->searchPageGroupPerformances = $searchPerformanceService->getPageGroups($requestDto->siteKey, $requestDto->buildSearchWindowRequest());
        return $responseDto;
    }

    /**
     * One page in depth: metrics and series with comparison, the queries it was shown for, its page group.
     *
     * @param SiteInsightsSearchPageDetailRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchPageDetailGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/pageDetail')]
    #[Summary('SiteInsights Search Page Detail')]
    public function getSiteInsightsSearchPageDetail(
        SiteInsightsSearchPageDetailRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchPageDetailGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSearchPageDetailGetResponseDto();
        $responseDto->searchPageDetail = $searchPerformanceService->getPageDetail($requestDto->siteKey, $requestDto->url, $requestDto->buildSearchWindowRequest());
        return $responseDto;
    }

    /**
     * One query in depth: metrics and series with comparison, the pages shown for it.
     *
     * @param SiteInsightsSearchQueryDetailRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @return SiteInsightsSearchQueryDetailGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/search/queryDetail')]
    #[Summary('SiteInsights Search Query Detail')]
    public function getSiteInsightsSearchQueryDetail(
        SiteInsightsSearchQueryDetailRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService
    ): SiteInsightsSearchQueryDetailGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSearchQueryDetailGetResponseDto();
        $responseDto->searchQueryDetail = $searchPerformanceService->getQueryDetail($requestDto->siteKey, $requestDto->query, $requestDto->buildSearchWindowRequest());
        return $responseDto;
    }

    /**
     * @param SiteInsightsSearchRowsRequestDto $requestDto
     * @param SearchPerformanceService $searchPerformanceService
     * @param string $dimension
     * @return SiteInsightsSearchRowsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    protected function buildSiteInsightsSearchRowsResponse(
        SiteInsightsSearchRowsRequestDto $requestDto,
        SearchPerformanceService $searchPerformanceService,
        string $dimension
    ): SiteInsightsSearchRowsGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSearchRowsGetResponseDto();
        $responseDto->searchPerformanceRows = $searchPerformanceService->getRows($requestDto->siteKey, $requestDto->buildSearchListRequest($dimension));
        return $responseDto;
    }
}
