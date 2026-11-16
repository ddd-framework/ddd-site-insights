<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:query',
    description: 'One search query of one site in depth (exact match): metrics and series with comparison, the pages shown for it (--format=json: property searchQueryDetail)'
)]
class SiteInsightsQuery extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()
            ->addArgument('query', InputArgument::REQUIRED, 'The query, exactly as the provider reports it (quote it)')
            ->addWindowOptions()
            ->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var SearchPerformanceService $searchPerformanceService */
            $searchPerformanceService = DDDService::instance()->getService(SearchPerformanceService::class);
            $searchQueryDetail = $searchPerformanceService->getQueryDetail(
                (string)$input->getArgument('site'),
                (string)$input->getArgument('query'),
                $this->buildWindowRequest($input)
            );
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchQueryDetail', $searchQueryDetail);
            } else {
                $output->writeln($this->buildWindowNote($searchQueryDetail->window, $searchQueryDetail->comparisonWindow ?? null));
                $this->writeTotalsLine($output, 'Query "' . $searchQueryDetail->query . '"', $searchQueryDetail->totals);
                $this->writeRowsTable($output, 'Pages of the query', $searchQueryDetail->pages);
                $this->writeRowsTable($output, 'Series', $searchQueryDetail->series);
            }
            return $this->determineExitCodeOfParts($searchQueryDetail->totals, $searchQueryDetail->series, $searchQueryDetail->pages);
        });
    }
}
