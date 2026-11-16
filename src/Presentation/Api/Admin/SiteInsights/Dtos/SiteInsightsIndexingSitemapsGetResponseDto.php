<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatuses;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsIndexingSitemapsGetResponseDto extends RestResponseDto
{
    /** @var SitemapStatuses The sitemap files of the site's property with their state */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SitemapStatuses $sitemapStatuses;
}
