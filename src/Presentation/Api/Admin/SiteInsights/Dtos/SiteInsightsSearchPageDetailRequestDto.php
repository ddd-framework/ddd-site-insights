<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\Validator\Constraints\Length;

/** A page of a site and a window */
class SiteInsightsSearchPageDetailRequestDto extends SiteInsightsSearchWindowRequestDto
{
    /** @var string The page URL; must belong to the site's property */
    #[Parameter(in: Parameter::QUERY, required: true)]
    #[Length(max: 2048)]
    public string $url;
}
