<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Indexing\SitemapStatuses;
use DDD\Domain\SiteInsights\Services\SearchIndexingService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:sitemaps',
    description: 'The sitemap files of one site with submitted URLs, errors, warnings and download times (--format=json: property sitemapStatuses)'
)]
class SiteInsightsSitemaps extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var SearchIndexingService $searchIndexingService */
            $searchIndexingService = DDDService::instance()->getService(SearchIndexingService::class);
            $sitemapStatuses = $searchIndexingService->getSitemapStatuses((string)$input->getArgument('site'));
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'sitemapStatuses', $sitemapStatuses);
            } else {
                $this->writeSitemapsTable($output, $sitemapStatuses);
            }
            return isset($sitemapStatuses->error) ? self::EXIT_OPERATIONAL_FAILURE : self::EXIT_SUCCESS;
        });
    }

    /**
     * @param OutputInterface $output
     * @param SitemapStatuses $sitemapStatuses
     * @return void
     */
    protected function writeSitemapsTable(OutputInterface $output, SitemapStatuses $sitemapStatuses): void
    {
        $output->writeln('<info>Sitemaps of ' . $sitemapStatuses->property . '</info>');
        if (isset($sitemapStatuses->error)) {
            $output->writeln('  failed: ' . $sitemapStatuses->error->cause . ' (' . $sitemapStatuses->error->message . ')');
            return;
        }
        $table = new Table($output);
        $table->setHeaders(['File', 'Type', 'Index', 'Pending', 'Submitted', 'Errors', 'Warnings', 'Last submitted', 'Last downloaded']);
        foreach ($sitemapStatuses->getElements() as $sitemapStatus) {
            $table->addRow([
                $sitemapStatus->path,
                $sitemapStatus->type ?? '',
                $sitemapStatus->isIndex ? 'yes' : '',
                $sitemapStatus->isPending ? 'yes' : '',
                $sitemapStatus->submitted,
                $sitemapStatus->errors,
                $sitemapStatus->warnings,
                isset($sitemapStatus->lastSubmitted) ? gmdate('Y-m-d H:i', $sitemapStatus->lastSubmitted->getTimestamp()) . ' UTC' : '',
                isset($sitemapStatus->lastDownloaded) ? gmdate('Y-m-d H:i', $sitemapStatus->lastDownloaded->getTimestamp()) . ' UTC' : '',
            ]);
        }
        $table->render();
        if (!count($sitemapStatuses->getElements())) {
            $output->writeln('  The provider lists no sitemap file for the property.');
        }
        $output->writeln('  ' . $this->buildIndexingCoverageNote($sitemapStatuses->coverage));
    }
}
