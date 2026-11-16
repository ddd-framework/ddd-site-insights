<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Regex;

/** The URLs of a site to inspect (each one not answered from the cache counts against the daily inspection quota) */
class SiteInsightsInspectionsPostRequestDto extends SiteInsightsSiteGetRequestDto
{
    /** @var string[] URLs of the site's property, 1 to budgets.maxInspectionUrls (default 20; more are refused with 400), each at most 2048 characters */
    #[Parameter(in: Parameter::BODY, required: true)]
    #[Count(min: 1)]
    public array $urls = [];

    /** @var string|null Language of the provider's texts, e.g. en or en-US */
    #[Parameter(in: Parameter::BODY, required: false)]
    #[Regex(pattern: '/^[a-z]{2}(-[A-Z]{2})?$/')]
    public ?string $languageCode = null;
}
