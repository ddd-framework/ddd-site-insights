<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Traits;

use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessCheckService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsAccessChecksGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSiteGetRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSitesGetResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;

/**
 * The sites endpoints of an admin API: the configured sites, and the live capability check of one site. An
 * application mounts them with one controller of its own, which sets the path prefix, the tag and the access:
 *
 *     #[Route('/siteInsights')]
 *     #[Tag(group: 'Website', name: 'SiteInsights', description: 'Search Console insights')]
 *     class AdminSiteInsightsSitesController extends AdminController
 *     {
 *         use SiteInsightsSitesControllerTrait;
 *     }
 *
 * Every action asks SiteInsightsAccessPolicyService first.
 */
trait SiteInsightsSitesControllerTrait
{
    use SiteInsightsViewerTrait;

    /**
     * The configured sites with their page groups and what their provider can answer.
     *
     * @param InsightsSitesService $insightsSitesService
     * @return SiteInsightsSitesGetResponseDto
     * @throws ForbiddenException
     * @throws InternalErrorException
     */
    #[Get('/sites')]
    #[Summary('SiteInsights Sites')]
    public function getSiteInsightsSites(InsightsSitesService $insightsSitesService): SiteInsightsSitesGetResponseDto
    {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsSitesGetResponseDto();
        $responseDto->insightsSites = $insightsSitesService->getSites();
        return $responseDto;
    }

    /**
     * Checks every capability of the site against the provider, live: key, token, property, permission level,
     * Search Analytics, sitemaps, URL inspection (one inspection is counted) and whether the call counters are shared.
     * States only, never a secret.
     *
     * @param SiteInsightsSiteGetRequestDto $requestDto
     * @param InsightsSitesService $insightsSitesService
     * @param SiteInsightsAccessCheckService $siteInsightsAccessCheckService
     * @return SiteInsightsAccessChecksGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws InternalErrorException
     * @throws BadRequestException
     * @throws SiteInsightsBudgetExceededException
     */
    #[Get('/sites/{siteKey}/accessChecks')]
    #[Summary('SiteInsights Site Access Checks')]
    public function getSiteInsightsAccessChecks(
        SiteInsightsSiteGetRequestDto $requestDto,
        InsightsSitesService $insightsSitesService,
        SiteInsightsAccessCheckService $siteInsightsAccessCheckService
    ): SiteInsightsAccessChecksGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsAccessChecksGetResponseDto();
        $responseDto->siteInsightsAccessChecks = $siteInsightsAccessCheckService->checkAccess(
            $insightsSitesService->getSite($requestDto->siteKey)
        );
        return $responseDto;
    }
}
