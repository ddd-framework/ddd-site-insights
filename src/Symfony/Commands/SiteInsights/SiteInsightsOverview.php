<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:overview',
    description: 'Search overview of one site: totals with comparison, series by day, top queries, pages, countries, devices (--format=json: property searchPerformanceOverview; exit 1 when a part failed)'
)]
class SiteInsightsOverview extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()->addWindowOptions()->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var SearchPerformanceService $searchPerformanceService */
            $searchPerformanceService = DDDService::instance()->getService(SearchPerformanceService::class);
            $searchPerformanceOverview = $searchPerformanceService->getOverview((string)$input->getArgument('site'), $this->buildWindowRequest($input));
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchPerformanceOverview', $searchPerformanceOverview);
            } else {
                $output->writeln($this->buildWindowNote($searchPerformanceOverview->window, $searchPerformanceOverview->comparisonWindow ?? null));
                $this->writeTotalsLine($output, 'Totals', $searchPerformanceOverview->totals);
                $this->writeRowsTable($output, 'Top queries', $searchPerformanceOverview->topQueries);
                $this->writeRowsTable($output, 'Top pages', $searchPerformanceOverview->topPages);
                $this->writeRowsTable($output, 'Countries', $searchPerformanceOverview->countries);
                $this->writeRowsTable($output, 'Devices', $searchPerformanceOverview->devices);
                $this->writeRowsTable($output, 'Series', $searchPerformanceOverview->series);
            }
            return $this->determineExitCodeOfParts(
                $searchPerformanceOverview->totals,
                $searchPerformanceOverview->series,
                $searchPerformanceOverview->topQueries,
                $searchPerformanceOverview->topPages,
                $searchPerformanceOverview->countries,
                $searchPerformanceOverview->devices
            );
        });
    }
}
