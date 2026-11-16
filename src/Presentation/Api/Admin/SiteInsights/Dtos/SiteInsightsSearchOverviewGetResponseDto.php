<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceOverview;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsSearchOverviewGetResponseDto extends RestResponseDto
{
    /** @var SearchPerformanceOverview The overview: window, comparison window, totals, series and top lists, each part with coverage or error */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public SearchPerformanceOverview $searchPerformanceOverview;
}
