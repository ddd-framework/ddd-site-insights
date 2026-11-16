<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Traits;

use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Services\SearchIndexingService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsIndexingSitemapsGetResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsInspectionsPostRequestDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsInspectionsPostResponseDto;
use DDD\Presentation\Api\Admin\SiteInsights\Dtos\SiteInsightsSiteGetRequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Post;

/**
 * The indexing endpoints of an admin API: the sitemap files of a site (a GET read, cached, no daily quota), and the
 * inspection of a bounded list of its URLs — a POST, because every inspection not answered from the cache counts
 * against the provider's daily URL inspection quota. Rule: anything that counts a URL inspection is never GET, so no
 * client refetches it on its own. An application mounts them with one controller of its own, which sets the path
 * prefix, the tag and the access:
 *
 *     #[Route('/siteInsights')]
 *     #[Tag(group: 'Website', name: 'SiteInsights', description: 'Search Console insights')]
 *     class AdminSiteInsightsIndexingController extends AdminController
 *     {
 *         use SiteInsightsIndexingControllerTrait;
 *     }
 *
 * Every action asks SiteInsightsAccessPolicyService first (inspecting: `canInspect()`). Each payload equals the JSON
 * of the matching `app:site-insights:*` command.
 */
trait SiteInsightsIndexingControllerTrait
{
    use SiteInsightsViewerTrait;

    /**
     * The sitemap files of the site's property: submitted URLs per content type, errors, warnings, last submitted and
     * last downloaded, with coverage or the list's error.
     *
     * @param SiteInsightsSiteGetRequestDto $requestDto
     * @param SearchIndexingService $searchIndexingService
     * @return SiteInsightsIndexingSitemapsGetResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Get('/sites/{siteKey}/indexing/sitemaps')]
    #[Summary('SiteInsights Indexing Sitemaps')]
    public function getSiteInsightsIndexingSitemaps(
        SiteInsightsSiteGetRequestDto $requestDto,
        SearchIndexingService $searchIndexingService
    ): SiteInsightsIndexingSitemapsGetResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsIndexingSitemapsGetResponseDto();
        $responseDto->sitemapStatuses = $searchIndexingService->getSitemapStatuses($requestDto->siteKey);
        return $responseDto;
    }

    /**
     * Inspects up to `budgets.maxInspectionUrls` URLs of the site (body `urls`, optional `languageCode`): the index
     * state of each, cached a day per URL, or the URL's own error. A POST: every inspection not answered from the
     * cache counts against the daily inspection quota.
     *
     * @param SiteInsightsInspectionsPostRequestDto $requestDto
     * @param SearchIndexingService $searchIndexingService
     * @return SiteInsightsInspectionsPostResponseDto
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws SiteInsightsBudgetExceededException
     * @throws SiteInsightsProviderUnavailableException
     */
    #[Post('/sites/{siteKey}/indexing/inspections')]
    #[Summary('SiteInsights Indexing Inspections')]
    public function postSiteInsightsIndexingInspections(
        SiteInsightsInspectionsPostRequestDto $requestDto,
        SearchIndexingService $searchIndexingService
    ): SiteInsightsInspectionsPostResponseDto {
        $this->requireSiteInsightsViewer();
        $responseDto = new SiteInsightsInspectionsPostResponseDto();
        $responseDto->urlIndexInspections = $searchIndexingService->inspectUrls(
            $requestDto->siteKey,
            array_map('strval', $requestDto->urls),
            $requestDto->languageCode
        );
        return $responseDto;
    }
}
