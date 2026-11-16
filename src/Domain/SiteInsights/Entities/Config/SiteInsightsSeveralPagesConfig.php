<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

/** `severalPagesForQuery`: top queries for which several pages of the site get a real share of the impressions */
class SiteInsightsSeveralPagesConfig extends SiteInsightsOpportunityRuleConfig
{
    public const string RULE_KEY = 'severalPagesForQuery';

    /** @var int Top queries (provider order) whose pages are asked */
    public int $topQueries = 50;

    /** @var int Rows asked per query in the query × page report */
    public int $pagesPerQueryMax = 10;

    /** @var int Fewest impressions per page and 28 days (scaled to the window) */
    public int $minImpressionsPerPage = 20;

    /** @var float Smallest share of the query's impressions in the report a page must have */
    public float $minShare = 0.1;

    /** @var int Fewest pages passing both thresholds */
    public int $minPages = 2;
}
