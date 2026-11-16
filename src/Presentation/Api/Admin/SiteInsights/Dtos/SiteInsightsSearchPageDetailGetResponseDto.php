<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchPageDetail;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchPageDetailGetResponseDto extends RestResponseDto
{
    /** @var SearchPageDetail One page in depth */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchPageDetail $searchPageDetail;
}
