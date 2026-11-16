<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchQueryDetail;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchQueryDetailGetResponseDto extends RestResponseDto
{
    /** @var SearchQueryDetail One query in depth */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchQueryDetail $searchQueryDetail;
}
