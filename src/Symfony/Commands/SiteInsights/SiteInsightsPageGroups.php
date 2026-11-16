<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Search\SearchPageGroupPerformance;
use DDD\Domain\SiteInsights\Entities\Search\SearchTotals;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:page-groups',
    description: 'One aggregate per configured page group of one site, with comparison; groups may overlap and are never summed (--format=json: property searchPageGroupPerformances)'
)]
class SiteInsightsPageGroups extends SiteInsightsCommand
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
            $searchPageGroupPerformances = $searchPerformanceService->getPageGroups((string)$input->getArgument('site'), $this->buildWindowRequest($input));
            $totalsOfGroups = array_map(
                static fn (SearchPageGroupPerformance $searchPageGroupPerformance): SearchTotals => $searchPageGroupPerformance->totals,
                array_values($searchPageGroupPerformances->getElements())
            );
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchPageGroupPerformances', $searchPageGroupPerformances);
                return $this->determineExitCodeOfParts(...$totalsOfGroups);
            }
            $output->writeln($this->buildWindowNote($searchPageGroupPerformances->window, $searchPageGroupPerformances->comparisonWindow ?? null));
            $table = new Table($output);
            $table->setHeaders(['Group', 'Clicks', 'Impressions', 'CTR', 'Position', 'Counterpart', 'Δ Clicks', 'Error']);
            foreach ($searchPageGroupPerformances->getElements() as $searchPageGroupPerformance) {
                $searchMetricsComparison = $searchPageGroupPerformance->totals->metrics ?? null;
                $table->addRow([
                    $searchPageGroupPerformance->pageGroup->key,
                    $searchMetricsComparison?->current->clicks ?? '',
                    $searchMetricsComparison?->current->impressions ?? '',
                    $searchMetricsComparison !== null ? sprintf('%.2f%%', $searchMetricsComparison->current->ctr * 100) : '',
                    isset($searchMetricsComparison->current->position) ? sprintf('%.1f', $searchMetricsComparison->current->position) : '',
                    $searchMetricsComparison?->counterpartState ?? '',
                    $this->formatClicksDelta($searchMetricsComparison),
                    $searchPageGroupPerformance->totals->error->cause ?? '',
                ]);
            }
            $table->render();
            $output->writeln('Groups may overlap and are never summed; page aggregation counts higher than the property totals.');
            if ($searchPageGroupPerformances->pageGroupsLeftOut > 0) {
                $output->writeln($searchPageGroupPerformances->pageGroupsLeftOut . ' groups beyond budgets.maxPageGroups were not asked.');
            }
            return $this->determineExitCodeOfParts(...$totalsOfGroups);
        });
    }
}
