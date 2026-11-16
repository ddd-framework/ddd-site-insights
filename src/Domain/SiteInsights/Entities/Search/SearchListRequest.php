<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

/**
 * One list a caller asks for: the window, the dimension the rows are grouped by, the filters, and which rows in which
 * order. Explicit parameters, not QueryOptions: sorting other than by clicks happens over the fetched rows only.
 */
class SearchListRequest extends SearchWindowRequest
{
    public const string DIMENSION_QUERY = 'query';
    public const string DIMENSION_PAGE = 'page';
    public const string DIMENSION_COUNTRY = 'country';
    public const string DIMENSION_DEVICE = 'device';

    /** @var string[] Dimensions a list can be grouped by */
    public const array LIST_DIMENSIONS = [self::DIMENSION_QUERY, self::DIMENSION_PAGE, self::DIMENSION_COUNTRY, self::DIMENSION_DEVICE];

    public const string SORT_BY_CLICKS = 'clicks';
    public const string SORT_BY_IMPRESSIONS = 'impressions';
    public const string SORT_BY_CTR = 'ctr';
    public const string SORT_BY_POSITION = 'position';

    /** @var string[] */
    public const array SORT_BY_VALUES = [self::SORT_BY_CLICKS, self::SORT_BY_IMPRESSIONS, self::SORT_BY_CTR, self::SORT_BY_POSITION];

    public const string SORT_DIRECTION_DESC = 'desc';
    public const string SORT_DIRECTION_ASC = 'asc';

    /** @var string[] */
    public const array DEVICES = ['desktop', 'mobile', 'tablet'];

    public const int DEFAULT_LIMIT = 50;
    public const int MAX_LIMIT = 1000;
    public const int MAX_OFFSET = 24999;

    /** @var string query, page, country or device */
    public string $dimension = self::DIMENSION_QUERY;

    /** @var string|null Only queries containing this text */
    public ?string $queryContains;

    /** @var string|null Only this query */
    public ?string $queryEquals;

    /** @var string|null Only pages whose URL contains this text */
    public ?string $pageContains;

    /** @var string|null Only this page URL */
    public ?string $pageEquals;

    /** @var string|null Only pages of this configured page group */
    public ?string $pageGroup;

    /** @var string|null Only this country (ISO 3166-1 alpha-3, lower case) */
    public ?string $country;

    /** @var string|null Only desktop, mobile or tablet */
    public ?string $device;

    /** @var int Rows, 1 to 1000 */
    public int $limit = self::DEFAULT_LIMIT;

    /** @var int First row, 0 to 24999 */
    public int $offset = 0;

    /** @var string clicks, impressions, ctr or position */
    public string $sortBy = self::SORT_BY_CLICKS;

    /** @var string|null desc or asc; unset: desc, asc for position */
    public ?string $sortDirection;

    /** @return string the sort direction asked, else desc (asc for position: 1 is the top) */
    public function getEffectiveSortDirection(): string
    {
        return $this->sortDirection ?? ($this->sortBy === self::SORT_BY_POSITION ? self::SORT_DIRECTION_ASC : self::SORT_DIRECTION_DESC);
    }

    /** @return bool whether the provider's own order (clicks, descending) answers the request */
    public function isSortedByProvider(): bool
    {
        return $this->sortBy === self::SORT_BY_CLICKS && $this->getEffectiveSortDirection() === self::SORT_DIRECTION_DESC;
    }
}
