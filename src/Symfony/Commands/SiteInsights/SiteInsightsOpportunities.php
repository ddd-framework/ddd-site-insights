<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunities;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunitiesRequest;
use DDD\Domain\SiteInsights\Entities\Opportunities\SearchOpportunity;
use DDD\Domain\SiteInsights\Services\SearchOpportunitiesService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:opportunities',
    description: 'The opportunity rules of one site and window: candidates with evidence, one result per kind (--format=json: property searchOpportunities; exit 1 when a kind failed)'
)]
class SiteInsightsOpportunities extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()
            ->addOption('kind', null, InputOption::VALUE_REQUIRED, 'One kind: ' . implode(', ', SearchOpportunity::KINDS) . ' (default: every kind)')
            ->addWindowOptions()
            ->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            $searchOpportunitiesRequest = new SearchOpportunitiesRequest();
            $this->applyWindowOptions($input, $searchOpportunitiesRequest);
            $kindOption = $input->getOption('kind');
            if ($kindOption !== null) {
                $searchOpportunitiesRequest->kind = (string)$kindOption;
            }
            /** @var SearchOpportunitiesService $searchOpportunitiesService */
            $searchOpportunitiesService = DDDService::instance()->getService(SearchOpportunitiesService::class);
            $searchOpportunities = $searchOpportunitiesService->getOpportunities((string)$input->getArgument('site'), $searchOpportunitiesRequest);
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'searchOpportunities', $searchOpportunities);
            } else {
                $this->writeOpportunitiesTables($output, $searchOpportunities);
            }
            foreach ($searchOpportunities->kinds->getElements() as $searchOpportunityKindResult) {
                if (isset($searchOpportunityKindResult->error)) {
                    return self::EXIT_OPERATIONAL_FAILURE;
                }
            }
            return self::EXIT_SUCCESS;
        });
    }

    /**
     * @param OutputInterface $output
     * @param SearchOpportunities $searchOpportunities
     * @return void one table per kind, then why a kind is empty or failed
     */
    protected function writeOpportunitiesTables(OutputInterface $output, SearchOpportunities $searchOpportunities): void
    {
        $output->writeln($this->buildWindowNote($searchOpportunities->window, $searchOpportunities->comparisonWindow ?? null));
        foreach ($searchOpportunities->kinds->getElements() as $searchOpportunityKindResult) {
            $output->writeln(sprintf('<info>%s</info> %d result(s), %d provider call(s)', $searchOpportunityKindResult->kind, $searchOpportunityKindResult->resultCount, $searchOpportunityKindResult->providerCalls));
            if (isset($searchOpportunityKindResult->error)) {
                $output->writeln('  failed: ' . $searchOpportunityKindResult->error->cause . ' (' . $searchOpportunityKindResult->error->message . ')' . ($searchOpportunityKindResult->isPartial ? ', results from part of the source' : ''));
            }
            if (isset($searchOpportunityKindResult->emptyReason)) {
                $output->writeln('  empty: ' . $searchOpportunityKindResult->emptyReason);
            }
            if ($searchOpportunityKindResult->resultCount > 0) {
                $table = new Table($output);
                $table->setHeaders(['Subject', 'Device', 'Clicks', 'Impressions', 'CTR', 'Position', 'Detail']);
                foreach ($searchOpportunities->getElements() as $searchOpportunity) {
                    if ($searchOpportunity->kind !== $searchOpportunityKindResult->kind) {
                        continue;
                    }
                    $table->addRow([
                        $searchOpportunity->query ?? $searchOpportunity->page ?? '',
                        $searchOpportunity->device ?? '',
                        $searchOpportunity->metrics->clicks,
                        $searchOpportunity->metrics->impressions,
                        sprintf('%.2f%%', $searchOpportunity->metrics->ctr * 100),
                        isset($searchOpportunity->metrics->position) ? sprintf('%.1f', $searchOpportunity->metrics->position) : '',
                        $this->buildOpportunityDetail($searchOpportunity),
                    ]);
                }
                $table->render();
            }
            $output->writeln('  ' . $searchOpportunityKindResult->note);
        }
    }

    /**
     * @param SearchOpportunity $searchOpportunity
     * @return string the baseline, the clicks delta or the page count of a result
     */
    protected function buildOpportunityDetail(SearchOpportunity $searchOpportunity): string
    {
        if (isset($searchOpportunity->baseline)) {
            return sprintf(
                'baseline %.2f%% (%s, positions %g–%g, %d rows)',
                $searchOpportunity->baseline->clickRate * 100,
                $searchOpportunity->baseline->baselineScope,
                $searchOpportunity->baseline->positionFrom,
                $searchOpportunity->baseline->positionTo,
                $searchOpportunity->baseline->rowCount
            );
        }
        if (isset($searchOpportunity->comparison)) {
            return 'Δ clicks ' . $this->formatClicksDelta($searchOpportunity->comparison);
        }
        if ($searchOpportunity->kind === SearchOpportunity::KIND_SEVERAL_PAGES_FOR_QUERY) {
            return count($searchOpportunity->evidenceRows->getElements()) . ' page row(s)';
        }
        return '';
    }
}
