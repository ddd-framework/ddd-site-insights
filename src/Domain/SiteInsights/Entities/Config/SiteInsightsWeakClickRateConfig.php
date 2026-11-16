<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

/** `weakClickRateForPosition`: queries whose click rate is far below the baseline of their position bucket */
class SiteInsightsWeakClickRateConfig extends SiteInsightsOpportunityRuleConfig
{
    public const string RULE_KEY = 'weakClickRateForPosition';

    /** @var int Fewest impressions per 28 days (scaled to the window) for a row to be judged and to count in a baseline */
    public int $minImpressions = 50;

    /** @var int Fewest rows a bucket needs for its baseline */
    public int $minRowsPerBucket = 5;

    /** @var float A row is a hit below this share of its bucket's click rate */
    public float $ratio = 0.5;

    /** @var bool Leave out queries matching the site's brand patterns */
    public bool $excludeBrand = true;

    /** @var bool A device bucket below minRowsPerBucket takes the baseline of all devices of the same position bucket */
    public bool $fallbackToAllDevices = true;

    /** @var int Most results */
    public int $maxResults = 50;
}
