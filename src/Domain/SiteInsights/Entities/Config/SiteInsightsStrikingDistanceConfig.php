<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

/** `strikingDistanceQuery`: queries ranking just below the first page with enough impressions */
class SiteInsightsStrikingDistanceConfig extends SiteInsightsOpportunityRuleConfig
{
    public const string RULE_KEY = 'strikingDistanceQuery';

    /** @var float Lowest average position (inclusive) */
    public float $positionFrom = 8.0;

    /** @var float Highest average position (inclusive) */
    public float $positionTo = 20.0;

    /** @var int Fewest impressions per 28 days (scaled to the window) */
    public int $minImpressions = 100;

    /** @var bool Leave out queries matching the site's brand patterns */
    public bool $excludeBrand = true;

    /** @var int Most results */
    public int $maxResults = 50;
}
