<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchRowsGetResponseDto extends RestResponseDto
{
    /** @var SearchPerformanceRows The rows with window, comparison window and coverage; with a comparison the counterpart per row */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchPerformanceRows $searchPerformanceRows;
}
