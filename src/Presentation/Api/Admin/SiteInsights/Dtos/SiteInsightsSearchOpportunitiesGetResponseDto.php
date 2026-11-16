<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunities;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchOpportunitiesGetResponseDto extends RestResponseDto
{
    /** @var SearchOpportunities The candidates of the opportunity rules with one result per kind */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchOpportunities $searchOpportunities;
}
