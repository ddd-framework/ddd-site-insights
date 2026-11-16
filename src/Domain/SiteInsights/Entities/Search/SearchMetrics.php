<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/** Metrics of one row or aggregate; the click rate is always clicks ÷ impressions of this aggregate */
class SearchMetrics extends ValueObject
{
    public const string AGGREGATION_BY_PROPERTY = 'byProperty';
    public const string AGGREGATION_BY_PAGE = 'byPage';

    /** @var int Clicks */
    public int $clicks = 0;

    /** @var int Impressions */
    public int $impressions = 0;

    /** @var float Click rate 0–1; 0 without impressions */
    public float $ctr = 0.0;

    /** @var float|null Average position (1 = top); unset when the provider or search type has none */
    public ?float $position;

    /** @var string byProperty or byPage, as the provider answered (SearchRowsCoverage::$responseAggregation) */
    public string $aggregation = self::AGGREGATION_BY_PROPERTY;

    /** @var bool false: the provider returned no row for this question (zeros are not observations) */
    public bool $hasData = true;

    /**
     * @param int $clicks
     * @param int $impressions
     * @param float|null $position
     * @param string $aggregation
     * @return static metrics whose click rate is computed from the integers
     */
    public static function create(int $clicks, int $impressions, ?float $position, string $aggregation): static
    {
        $searchMetrics = new static();
        $searchMetrics->clicks = $clicks;
        $searchMetrics->impressions = $impressions;
        $searchMetrics->ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
        if ($position !== null) {
            $searchMetrics->position = $position;
        }
        $searchMetrics->aggregation = $aggregation;
        return $searchMetrics;
    }

    /**
     * @param string $aggregation
     * @return static zeros the provider did not report (hasData false: not an observation)
     */
    public static function createWithoutData(string $aggregation): static
    {
        $searchMetrics = static::create(0, 0, null, $aggregation);
        $searchMetrics->hasData = false;
        return $searchMetrics;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
