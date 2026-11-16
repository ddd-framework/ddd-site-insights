<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Infrastructure\Base\DateTime\Date;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Validation\Constraints\Choice;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * A site and a window: a preset or explicit days (provider days, `Y-m-d` in the provider's day zone), a comparison,
 * search type and data state. Days are framework dates (the calendar day as written, at midnight UTC); the request copies
 * them through SearchDays, and the service validates every value again.
 */
class SiteInsightsSearchWindowRequestDto extends SiteInsightsSiteGetRequestDto
{
    /** @var string|null 7d, 28d (default), 90d, 6m, 12m or 16m, ending on the last day with data; ignored with from and to */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['7d', '28d', '90d', '6m', '12m', '16m'])]
    public ?string $preset = null;

    /** @var Date|null First day (Y-m-d, a day of the provider's day zone); with to instead of the preset */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public ?Date $from = null;

    /** @var Date|null Last day (Y-m-d, a day of the provider's day zone); at most the last day with data of the data state */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public ?Date $to = null;

    /** @var string|null none (default), previousPeriod or previousYear (52 weeks earlier, weekdays aligned) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['none', 'previousPeriod', 'previousYear'])]
    public ?string $compare = null;

    /** @var string|null web (default), image, video, news, discover or googleNews */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['web', 'image', 'video', 'news', 'discover', 'googleNews'])]
    public ?string $searchType = null;

    /** @var string|null final (default: settled days only) or all (fresh days that may still change) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['final', 'all'])]
    public ?string $dataState = null;

    /**
     * @return SearchWindowRequest the window the parameters ask for (defaults for the parameters left out)
     * @throws BadRequestException for a day that does not exist
     */
    public function buildSearchWindowRequest(): SearchWindowRequest
    {
        $searchWindowRequest = new SearchWindowRequest();
        $this->applyWindowParameters($searchWindowRequest);
        return $searchWindowRequest;
    }

    /**
     * @param SearchWindowRequest $searchWindowRequest
     * @return void
     * @throws BadRequestException
     */
    protected function applyWindowParameters(SearchWindowRequest $searchWindowRequest): void
    {
        foreach (['preset', 'compare', 'searchType', 'dataState'] as $parameterName) {
            if ($this->$parameterName !== null) {
                $searchWindowRequest->$parameterName = $this->$parameterName;
            }
        }
        foreach (['from', 'to'] as $dayParameterName) {
            $dayValue = $this->$dayParameterName;
            if ($dayValue === null) {
                continue;
            }
            // a fresh date of the same day: the hydrated one is never handed on or modified
            $searchWindowRequest->$dayParameterName = SearchDays::toDate(SearchDays::fromDate($dayValue));
        }
    }
}
