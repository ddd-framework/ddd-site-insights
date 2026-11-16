<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessChecks;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsAccessChecksGetResponseDto extends RestResponseDto
{
    /** @var SiteInsightsAccessChecks The state of every capability of the site */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SiteInsightsAccessChecks $siteInsightsAccessChecks;
}
