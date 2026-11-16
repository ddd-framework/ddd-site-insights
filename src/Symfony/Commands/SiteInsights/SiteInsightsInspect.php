<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Indexing\UrlIndexInspections;
use DDD\Domain\SiteInsights\Services\SearchIndexingService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:inspect',
    description: 'Inspects URLs of one site (at most budgets.maxInspectionUrls; each one not cached counts against the daily inspection quota; exit 1 when a URL failed; --format=json: property urlIndexInspections)'
)]
class SiteInsightsInspect extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()
            ->addArgument('urls', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'URLs of the site\'s property (a bounded list; more than budgets.maxInspectionUrls are refused)')
            ->addOption('languageCode', null, InputOption::VALUE_REQUIRED, 'Language of the provider\'s texts, e.g. en or en-US')
            ->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var SearchIndexingService $searchIndexingService */
            $searchIndexingService = DDDService::instance()->getService(SearchIndexingService::class);
            $languageCode = $input->getOption('languageCode');
            $urlIndexInspections = $searchIndexingService->inspectUrls(
                (string)$input->getArgument('site'),
                array_map('strval', (array)$input->getArgument('urls')),
                $languageCode !== null ? (string)$languageCode : null
            );
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'urlIndexInspections', $urlIndexInspections);
            } else {
                $this->writeInspectionsTable($output, $urlIndexInspections);
            }
            return $urlIndexInspections->hasFailures() ? self::EXIT_OPERATIONAL_FAILURE : self::EXIT_SUCCESS;
        });
    }

    /**
     * @param OutputInterface $output
     * @param UrlIndexInspections $urlIndexInspections
     * @return void
     */
    protected function writeInspectionsTable(OutputInterface $output, UrlIndexInspections $urlIndexInspections): void
    {
        $table = new Table($output);
        $table->setHeaders(['URL', 'Verdict', 'Coverage state', 'Indexing', 'Fetch', 'Robots.txt', 'Last crawl', 'Google canonical', 'Cached / error']);
        foreach ($urlIndexInspections->getElements() as $urlIndexInspection) {
            if (isset($urlIndexInspection->error)) {
                $table->addRow([$urlIndexInspection->url, '', '', '', '', '', '', '', 'failed: ' . $urlIndexInspection->error->cause . ' (' . $urlIndexInspection->error->message . ')']);
                continue;
            }
            $table->addRow([
                $urlIndexInspection->url,
                $urlIndexInspection->verdict ?? '',
                $urlIndexInspection->coverageState ?? '',
                $urlIndexInspection->indexingState ?? '',
                $urlIndexInspection->pageFetchState ?? '',
                $urlIndexInspection->robotsTxtState ?? '',
                isset($urlIndexInspection->lastCrawlTime) ? gmdate('Y-m-d H:i', $urlIndexInspection->lastCrawlTime->getTimestamp()) . ' UTC' : '',
                $urlIndexInspection->googleCanonical ?? '',
                $urlIndexInspection->fromCache ? 'cached' : '',
            ]);
        }
        $table->render();
        $output->writeln(sprintf(
            '  %s; %d inspection(s) counted against the daily quota (counters %s)%s',
            $this->buildIndexingCoverageNote($urlIndexInspections->coverage),
            $urlIndexInspections->coverage->inspectionsCounted,
            $urlIndexInspections->inspectionCounterScope,
            $urlIndexInspections->duplicateUrlsDropped > 0 ? '; ' . $urlIndexInspections->duplicateUrlsDropped . ' duplicate URL(s) inspected once' : ''
        ));
    }
}
