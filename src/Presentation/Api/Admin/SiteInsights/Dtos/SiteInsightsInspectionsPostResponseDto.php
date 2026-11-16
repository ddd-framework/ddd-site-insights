<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspections;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class SiteInsightsInspectionsPostResponseDto extends RestResponseDto
{
    /** @var UrlIndexInspections The index state or the error of every URL */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public UrlIndexInspections $urlIndexInspections;
}
