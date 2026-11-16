<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Fixture\Controller;

use DDD\Presentation\Api\Admin\SiteInsights\Traits\SiteInsightsSearchControllerTrait;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Tag;
use DDD\Presentation\Base\Router\Routes\Route;

/**
 * The search performance endpoints as an application mounts them (source of the committed schema
 * web/schema/site-insights.openapi.json).
 */
#[Route('/siteInsights')]
#[Tag(group: 'SiteInsights', name: 'SiteInsights', description: 'Search Console insights: sites, search performance and indexing')]
class FixtureSiteInsightsSearchController extends HttpController
{
    use SiteInsightsSearchControllerTrait;
}
