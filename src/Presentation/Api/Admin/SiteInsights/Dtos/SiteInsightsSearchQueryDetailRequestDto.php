<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\Validator\Constraints\Length;

/** A query of a site (exact match) and a window */
class SiteInsightsSearchQueryDetailRequestDto extends SiteInsightsSearchWindowRequestDto
{
    /** @var string The query, exactly as the provider reports it */
    #[Parameter(in: Parameter::QUERY, required: true)]
    #[Length(max: 400)]
    public string $query;
}
