<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;

/** One filter of Google's `dimensionFilterGroups[0].filters` */
class GoogleSearchAnalyticsFilter extends ValueObject
{
    /** @var string query, page, country, device, searchAppearance */
    public string $dimension;

    /** @var string equals, contains, notEquals, notContains, includingRegex, excludingRegex */
    public string $operator;

    /** @var string The value or RE2 expression */
    public string $expression;

    /**
     * @param string $dimension
     * @param string $operator
     * @param string $expression
     * @return static
     */
    public static function create(string $dimension, string $operator, string $expression): static
    {
        $googleSearchAnalyticsFilter = new static();
        $googleSearchAnalyticsFilter->dimension = $dimension;
        $googleSearchAnalyticsFilter->operator = $operator;
        $googleSearchAnalyticsFilter->expression = $expression;
        return $googleSearchAnalyticsFilter;
    }

    /** The order of filters in a question: by dimension, then operator, then expression */
    public function buildSortKey(): string
    {
        return $this->dimension . "\0" . $this->operator . "\0" . $this->expression;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', ($this->dimension ?? '') . "\0" . ($this->operator ?? '') . "\0" . ($this->expression ?? '')));
    }
}
