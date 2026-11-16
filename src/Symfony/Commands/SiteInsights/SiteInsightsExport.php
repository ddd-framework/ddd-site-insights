<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\SiteInsights\Entities\Search\SearchExportRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Services\SearchPerformanceService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Services\DDDService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:site-insights:export',
    description: 'Pages a report of one site to its end within budgets.callsPerOperation.export: NDJSON rows to --output or stdout, then the summary (property searchExportSummary); retries retryable failures (budgets.exportRetries); exit 1 when it stopped early, continue with --resumeAfter=<lastFinishedDay>'
)]
class SiteInsightsExport extends SiteInsightsCommand
{
    protected function configure(): void
    {
        $this->addSiteArgument()
            ->addWindowOptions()
            ->addOption('dimensions', null, InputOption::VALUE_REQUIRED, 'Comma-separated subset of query, page, country, device, date', 'query,page')
            ->addOption('byDay', null, InputOption::VALUE_NEGATABLE, 'One window per day (default: on when query and page are asked together)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'File the NDJSON rows go to (default: stdout, before the summary)')
            ->addOption('resumeAfter', null, InputOption::VALUE_REQUIRED, 'Continue after this day (Y-m-d, the lastFinishedDay of a run that ended early); give the same window (--from/--to of its summary)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runGuarded($input, $output, function () use ($input, $output): int {
            $this->authenticateAndRequireViewer($output);
            $searchExportRequest = new SearchExportRequest();
            $this->applyWindowOptions($input, $searchExportRequest);
            $searchExportRequest->dimensions = array_values(array_filter(array_map('trim', explode(',', (string)$input->getOption('dimensions')))));
            if ($input->getOption('byDay') !== null) {
                $searchExportRequest->byDay = (bool)$input->getOption('byDay');
            }
            $resumeAfterOption = $input->getOption('resumeAfter');
            if ($resumeAfterOption !== null) {
                if (!SearchDays::isValidDay((string)$resumeAfterOption)) {
                    throw new BadRequestException('--resumeAfter must be a day written Y-m-d');
                }
                $searchExportRequest->resumeAfter = SearchDays::toDate((string)$resumeAfterOption);
            }
            $outputPath = $input->getOption('output');
            $rowsFileHandle = null;
            if ($outputPath !== null) {
                $rowsFileHandle = fopen((string)$outputPath, 'wb');
                if ($rowsFileHandle === false) {
                    throw new BadRequestException('--output cannot be written: ' . mb_substr((string)$outputPath, 0, 200));
                }
            }
            $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $writeRow = static function (SearchPerformanceRow $searchPerformanceRow) use ($rowsFileHandle, $output, $jsonFlags): void {
                $rowLine = (string)json_encode($searchPerformanceRow->toObject(cached: false, forPersistence: false), $jsonFlags);
                if ($rowsFileHandle !== null) {
                    fwrite($rowsFileHandle, $rowLine . "\n");
                    return;
                }
                $output->writeln($rowLine);
            };
            try {
                /** @var SearchPerformanceService $searchPerformanceService */
                $searchPerformanceService = DDDService::instance()->getService(SearchPerformanceService::class);
                $searchExportSummary = $searchPerformanceService->export((string)$input->getArgument('site'), $searchExportRequest, $writeRow);
            } finally {
                if ($rowsFileHandle !== null) {
                    fclose($rowsFileHandle);
                }
            }
            $output->writeln((string)json_encode(['searchExportSummary' => $searchExportSummary->toObject(cached: false, forPersistence: false)], $jsonFlags));
            return $searchExportSummary->limitReached || isset($searchExportSummary->error) ? self::EXIT_OPERATIONAL_FAILURE : self::EXIT_SUCCESS;
        });
    }
}
