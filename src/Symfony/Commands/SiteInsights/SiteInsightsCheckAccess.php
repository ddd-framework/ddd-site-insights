<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Access\SiteInsightsAccessChecks;
use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessCheckService;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:check-access',
    description: 'Checks every provider capability of one site or of all sites, live; prints states only (exit 1 when one failed)'
)]
class SiteInsightsCheckAccess extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument(false)->addFormatOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $format = $this->getFormat($input);
            $this->authenticateAndRequireViewer($output);
            /** @var InsightsSitesService $insightsSitesService */
            $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
            /** @var SiteInsightsAccessCheckService $siteInsightsAccessCheckService */
            $siteInsightsAccessCheckService = DDDService::instance()->getService(SiteInsightsAccessCheckService::class);
            $insightsSites = $input->getArgument('site') !== null
                ? [$this->getSite($input)]
                : $insightsSitesService->getSites()->getElements();
            $siteInsightsAccessChecks = new SiteInsightsAccessChecks();
            foreach ($insightsSites as $insightsSite) {
                $siteAccessChecks = $siteInsightsAccessCheckService->checkAccess($insightsSite);
                foreach ($siteAccessChecks->getElements() as $siteInsightsAccessCheck) {
                    $siteInsightsAccessChecks->add($siteInsightsAccessCheck);
                }
            }
            if ($format !== self::FORMAT_TABLE) {
                $this->writePayload($input, $output, 'siteInsightsAccessChecks', $siteInsightsAccessChecks);
            } else {
                $table = new Table($output);
                $table->setHeaders(['Site', 'Capability', 'State', 'Cause', 'Detail']);
                foreach ($siteInsightsAccessChecks->getElements() as $siteInsightsAccessCheck) {
                    $table->addRow([
                        $siteInsightsAccessCheck->siteKey,
                        $siteInsightsAccessCheck->capability,
                        $siteInsightsAccessCheck->state,
                        $siteInsightsAccessCheck->cause ?? '',
                        $siteInsightsAccessCheck->detail ?? '',
                    ]);
                }
                $table->render();
            }
            return $siteInsightsAccessChecks->hasFailures() ? self::EXIT_OPERATIONAL_FAILURE : self::EXIT_SUCCESS;
        });
    }
}
