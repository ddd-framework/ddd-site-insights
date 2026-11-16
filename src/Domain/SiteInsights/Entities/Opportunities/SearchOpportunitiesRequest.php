<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Opportunities;

use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;

/** The window of the opportunity rules and, optionally, the one kind to evaluate */
class SearchOpportunitiesRequest extends SearchWindowRequest
{
    /** @var string|null One kind (SearchOpportunity::KINDS); unset: every kind */
    public ?string $kind;
}
