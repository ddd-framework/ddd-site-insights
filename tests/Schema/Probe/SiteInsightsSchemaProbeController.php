<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Schema\Probe;

use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\OpenApi\Attributes\Tag;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Route;

/** Documents the B0 report classes for the schema gate (not part of the committed schema) */
#[Route('/siteInsightsSchemaProbe')]
#[Tag(group: 'SiteInsights', name: 'SiteInsightsSchemaProbe', description: 'Schema gate of the report classes')]
class SiteInsightsSchemaProbeController extends HttpController
{
    /**
     * The report classes.
     *
     * @return SiteInsightsSchemaProbeGetResponseDto
     */
    #[Get('/reports')]
    #[Summary('SiteInsights Schema Probe')]
    public function getReports(): SiteInsightsSchemaProbeGetResponseDto
    {
        return new SiteInsightsSchemaProbeGetResponseDto();
    }
}
