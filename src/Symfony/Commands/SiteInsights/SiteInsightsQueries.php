<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:site-insights:queries',
    description: 'Search queries of one site (anonymised queries omitted), with the comparison counterpart per row when --compare is set (--format=json: property searchPerformanceRows)'
)]
class SiteInsightsQueries extends SiteInsightsRowsCommand
{
    public const string DIMENSION = SearchListRequest::DIMENSION_QUERY;
}
