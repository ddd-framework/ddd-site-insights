<?php

declare(strict_types=1);

namespace DDD\Presentation\Api\Admin\SiteInsights\Dtos;

use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Validation\Constraints\Choice;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * A list of a site: the window, filters (all must match), which rows and in which order. Explicit parameters, not
 * QueryOptions: a sort other than clicks descending sorts the rows fetched up to budgets.sortFetchCeiling only
 * (`coverage.sortScope`).
 */
class SiteInsightsSearchRowsRequestDto extends SiteInsightsSearchWindowRequestDto
{
    /** @var string|null Only queries containing this text (anonymised queries are never matched) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Length(max: 400)]
    public ?string $queryContains = null;

    /** @var string|null Only this query */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Length(max: 400)]
    public ?string $queryEquals = null;

    /** @var string|null Only pages whose URL contains this text */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Length(max: 2048)]
    public ?string $pageContains = null;

    /** @var string|null Only this page URL */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Length(max: 2048)]
    public ?string $pageEquals = null;

    /** @var string|null Only pages of this configured page group (key) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Regex(pattern: '/^[a-z0-9][a-z0-9-]{0,62}$/')]
    public ?string $pageGroup = null;

    /** @var string|null Only this country (ISO 3166-1 alpha-3, lower case, e.g. deu) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Regex(pattern: '/^[a-z]{3}$/')]
    public ?string $country = null;

    /** @var string|null Only desktop, mobile or tablet */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['desktop', 'mobile', 'tablet'])]
    public ?string $device = null;

    /** @var int|null Rows, 1 to 1000 (default 50) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Range(min: 1, max: 1000)]
    public ?int $limit = null;

    /** @var int|null First row, 0 to 24999 (default 0) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Range(min: 0, max: 24999)]
    public ?int $offset = null;

    /** @var string|null clicks (default), impressions, ctr or position */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['clicks', 'impressions', 'ctr', 'position'])]
    public ?string $sortBy = null;

    /** @var string|null desc or asc (default desc, asc for position) */
    #[Parameter(in: Parameter::QUERY, required: false)]
    #[Choice(choices: ['desc', 'asc'])]
    public ?string $sortDirection = null;

    /**
     * @param string $dimension query, page, country or device (the route decides)
     * @return SearchListRequest
     * @throws BadRequestException
     */
    public function buildSearchListRequest(string $dimension): SearchListRequest
    {
        $searchListRequest = new SearchListRequest();
        $this->applyWindowParameters($searchListRequest);
        $searchListRequest->dimension = $dimension;
        foreach (['queryContains', 'queryEquals', 'pageContains', 'pageEquals', 'pageGroup', 'country', 'device', 'sortBy', 'sortDirection', 'limit', 'offset'] as $parameterName) {
            if ($this->$parameterName !== null) {
                $searchListRequest->$parameterName = $this->$parameterName;
            }
        }
        return $searchListRequest;
    }
}
