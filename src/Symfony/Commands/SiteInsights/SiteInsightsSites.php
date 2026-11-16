<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:sites',
    description: 'Lists the configured site insights sites (--format=json: the payload of the sites endpoint, property insightsSites)'
)]
class SiteInsightsSites extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var InsightsSitesService $insightsSitesService */
            $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
            $insightsSites = $insightsSitesService->getSites();
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'insightsSites', $insightsSites);
                return self::EXIT_SUCCESS;
            }
            $table = new Table($output);
            $table->setHeaders(['Key', 'Label', 'Provider', 'Property', 'Credentials', 'Page groups']);
            foreach ($insightsSites->getElements() as $insightsSite) {
                $table->addRow([
                    $insightsSite->key,
                    $insightsSite->label,
                    $insightsSite->provider,
                    $insightsSite->property,
                    $insightsSite->credentialsEntryName . ' (revision ' . $insightsSite->credentialsRevision . ')',
                    count($insightsSite->pageGroups->getElements()),
                ]);
            }
            $table->render();
            if (!count($insightsSites->getElements())) {
                $output->writeln('No site is configured (config/app/SiteInsights/sites.php).');
            }
            return self::EXIT_SUCCESS;
        });
    }
}
