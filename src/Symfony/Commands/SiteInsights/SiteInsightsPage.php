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
    name: 'app:site-insights:page',
    description: 'One page of one site in depth: metrics and series with comparison, its queries, its page group (--format=json: property searchPageDetail)'
)]
class SiteInsightsPage extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()
            ->addArgument('url', InputArgument::REQUIRED, 'The page URL; must belong to the site\'s property')
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
            $searchPageDetail = $searchPerformanceService->getPageDetail(
                (string)$input->getArgument('site'),
                (string)$input->getArgument('url'),
                $this->buildWindowRequest($input)
            );
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchPageDetail', $searchPageDetail);
            } else {
                $output->writeln($searchPageDetail->page . (isset($searchPageDetail->pageGroup) ? ' (page group ' . $searchPageDetail->pageGroup->key . ')' : ''));
                $output->writeln($this->buildWindowNote($searchPageDetail->window, $searchPageDetail->comparisonWindow ?? null));
                $this->writeTotalsLine($output, 'Page', $searchPageDetail->totals);
                $this->writeRowsTable($output, 'Queries of the page', $searchPageDetail->queries);
                $this->writeRowsTable($output, 'Series', $searchPageDetail->series);
            }
            return $this->determineExitCodeOfParts($searchPageDetail->totals, $searchPageDetail->series, $searchPageDetail->queries);
        });
    }
}
