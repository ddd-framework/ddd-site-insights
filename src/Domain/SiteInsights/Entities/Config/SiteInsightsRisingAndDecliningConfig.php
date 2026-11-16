<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

/** `risingQuery`, `decliningQuery`, `risingPage`, `decliningPage`: clicks that changed between the two periods */
class SiteInsightsRisingAndDecliningConfig extends SiteInsightsOpportunityRuleConfig
{
    public const string RULE_KEY = 'risingAndDeclining';

    /** @var int Top keys (provider order) taken from each period */
    public int $candidatesPerPeriod = 100;

    /** @var int Fewest clicks in either period per 28 days (scaled to the window) */
    public int $minClicksEitherPeriod = 10;

    /** @var int Smallest change of clicks per 28 days (scaled to the window) */
    public int $minAbsoluteDelta = 5;

    /** @var float Smallest change relative to the previous period's clicks */
    public float $minRelativeDelta = 0.3;

    /** @var int Most results per kind */
    public int $maxResults = 25;
}
