<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base of the list commands (queries, pages, countries, devices): window, filter, list and format options; JSON is
 * the payload of the matching list endpoint (property searchPerformanceRows). Exit 1 when the list failed.
 */
abstract class SiteInsightsRowsCommand extends SiteInsightsCommand
{
    /** @var string The dimension of the list (SearchListRequest::DIMENSION_*) */
    public const string DIMENSION = '';

    protected function configure(): void
    {
        $this->addSiteArgument()->addWindowOptions()->addFilterOptions()->addListOptions()->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var SearchPerformanceService $searchPerformanceService */
            $searchPerformanceService = DDDService::instance()->getService(SearchPerformanceService::class);
            $searchPerformanceRows = $searchPerformanceService->getRows((string)$input->getArgument('site'), $this->buildListRequest($input, static::DIMENSION));
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchPerformanceRows', $searchPerformanceRows);
            } else {
                $this->writeRowsTable($output, ucfirst(static::DIMENSION) . ' rows', $searchPerformanceRows);
            }
            return $this->determineExitCodeOfParts($searchPerformanceRows);
        });
    }
}
