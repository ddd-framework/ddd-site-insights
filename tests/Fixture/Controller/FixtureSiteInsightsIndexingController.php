<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Fixture\Controller;

use DDD\Presentation\Api\Admin\SiteInsights\Traits\SiteInsightsIndexingControllerTrait;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Tag;
use DDD\Presentation\Base\Router\Routes\Route;

/**
 * The indexing endpoints as an application mounts them (source of the committed schema
 * web/schema/site-insights.openapi.json).
 */
#[Route('/siteInsights')]
#[Tag(group: 'SiteInsights', name: 'SiteInsights', description: 'Search Console insights: sites, search performance and indexing')]
class FixtureSiteInsightsIndexingController extends HttpController
{
    use SiteInsightsIndexingControllerTrait;
}
