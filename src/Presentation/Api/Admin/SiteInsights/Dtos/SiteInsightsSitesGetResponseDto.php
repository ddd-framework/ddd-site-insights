<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Sites\InsightsSites;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSitesGetResponseDto extends RestResponseDto
{
    /** @var InsightsSites The configured sites with their page groups and provider capabilities */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public InsightsSites $insightsSites;
}
