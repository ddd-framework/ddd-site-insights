<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:site-insights:devices',
    description: 'Devices of one site, with the comparison counterpart per row when --compare is set (--format=json: property searchPerformanceRows)'
)]
class SiteInsightsDevices extends SiteInsightsRowsCommand
{
    public const string DIMENSION = SearchListRequest::DIMENSION_DEVICE;
}
