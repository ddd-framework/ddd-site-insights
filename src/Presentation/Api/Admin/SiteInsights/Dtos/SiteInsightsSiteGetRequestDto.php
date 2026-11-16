<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/** Names a configured site */
class SiteInsightsSiteGetRequestDto extends RequestDto
{
    /** @var string Key of the site (config SiteInsights.sites) */
    #[Parameter(in: Parameter::PATH, required: true)]
    #[Length(max: 64)]
    #[Regex(pattern: '/^[a-z0-9][a-z0-9-]{0,62}$/')]
    public string $siteKey;
}
