<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunitiesRequest;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Validation\Constraints\Choice;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/** A site, a window and, optionally, the one opportunity kind to evaluate */
class SiteInsightsSearchOpportunitiesRequestDto extends SiteInsightsSearchWindowRequestDto
{
    /** @var string|null One kind; left out: every kind */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['strikingDistanceQuery', 'weakClickRateForPosition', 'severalPagesForQuery', 'risingQuery', 'decliningQuery', 'risingPage', 'decliningPage'])]
    public ?string $kind = null;

    /**
     * @return SearchOpportunitiesRequest the window and the kind the parameters ask for
     * @throws BadRequestException for a day that does not exist
     */
    public function buildSearchOpportunitiesRequest(): SearchOpportunitiesRequest
    {
        $searchOpportunitiesRequest = new SearchOpportunitiesRequest();
        $this->applyWindowParameters($searchOpportunitiesRequest);
        if ($this->kind !== null) {
            $searchOpportunitiesRequest->kind = $this->kind;
        }
        return $searchOpportunitiesRequest;
    }
}
