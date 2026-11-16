<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchPageGroupPerformances;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchPageGroupsGetResponseDto extends RestResponseDto
{
    /** @var SearchPageGroupPerformances One aggregate per configured page group (page aggregation, may overlap, never summed) */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchPageGroupPerformances $searchPageGroupPerformances;
}
