<?php

declare(strict_types=1);

namespace DDD\Symfony\Commands\SiteInsights;

use DDD\Domain\Base\Entities\ObjectSet;
use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\SiteInsights\Entities\Indexing\IndexingCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchComparisonWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchListRequest;
use DDD\Domain\SiteInsights\Entities\Search\SearchMetricsComparison;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Entities\Search\SearchRowsCoverage;
use DDD\Domain\SiteInsights\Entities\Search\SearchTotals;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindow;
use DDD\Domain\SiteInsights\Entities\Search\SearchWindowRequest;
use DDD\Domain\SiteInsights\Entities\Sites\InsightsSite;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsBudgetExceededException;
use DDD\Domain\SiteInsights\Exceptions\SiteInsightsProviderUnavailableException;
use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Domain\SiteInsights\Services\SiteInsightsAccessPolicyService;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Services\AuthService;
use DDD\Infrastructure\Services\DDDService;
use DDD\Infrastructure\Traits\Serializer\SerializerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base of the site insights console commands: how agents and developers read the module from a terminal. Commands
 * run as the application's account for CLI operations and ask the access policy like every endpoint (no rights
 * bypass). JSON output is the payload of the matching admin endpoint under the same property name.
 *
 * Exit codes: 0 success, 1 an operational failure (provider, budget, a failed capability), 2 invalid input.
 */
abstract class SiteInsightsCommand extends Command
{
    public const string FORMAT_TABLE = 'table';
    public const string FORMAT_JSON = 'json';
    public const string FORMAT_TOON = 'toon';

    /** @var string[] */
    public const array FORMATS = [self::FORMAT_TABLE, self::FORMAT_JSON, self::FORMAT_TOON];

    public const int EXIT_SUCCESS = Command::SUCCESS;
    public const int EXIT_OPERATIONAL_FAILURE = Command::FAILURE;
    public const int EXIT_INVALID_INPUT = Command::INVALID;

    /** --format=table|json|toon, --pretty */
    protected function addFormatOptions(): static
    {
        return $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'table, json (the payload of the matching admin endpoint) or toon (the same, row sets in TOON)', self::FORMAT_TABLE)
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Indent JSON output');
    }

    /** The `site` argument: a key of SiteInsights.sites */
    protected function addSiteArgument(bool $required = true): static
    {
        return $this->addArgument(
            'site',
            $required ? InputArgument::REQUIRED : InputArgument::OPTIONAL,
            'Key of the site (config SiteInsights.sites)'
        );
    }

    /** --preset, --since=<days>, --from, --to (Y-m-d, provider days), --compare, --searchType, --dataState */
    protected function addWindowOptions(): static
    {
        return $this
            ->addOption('preset', null, InputOption::VALUE_REQUIRED, '7d, 28d, 90d, 6m, 12m or 16m', SearchWindowRequest::PRESET_28_DAYS)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Window of this many days ending on --to or the last day with data')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'First day, Y-m-d in the provider\'s day zone; with --to instead of --preset')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Last day, Y-m-d in the provider\'s day zone')
            ->addOption('compare', null, InputOption::VALUE_REQUIRED, 'none, previousPeriod or previousYear', SearchWindowRequest::COMPARE_NONE)
            ->addOption('searchType', null, InputOption::VALUE_REQUIRED, 'web, image, video, news, discover or googleNews', SearchWindowRequest::SEARCH_TYPE_WEB)
            ->addOption('dataState', null, InputOption::VALUE_REQUIRED, 'final (settled days) or all (fresh days that may change)', SearchWindowRequest::DATA_STATE_FINAL);
    }

    /** --queryContains, --queryEquals, --pageContains, --pageEquals, --pageGroup, --country, --device */
    protected function addFilterOptions(): static
    {
        return $this
            ->addOption('queryContains', null, InputOption::VALUE_REQUIRED, 'Only queries containing this text')
            ->addOption('queryEquals', null, InputOption::VALUE_REQUIRED, 'Only this query')
            ->addOption('pageContains', null, InputOption::VALUE_REQUIRED, 'Only pages whose URL contains this text')
            ->addOption('pageEquals', null, InputOption::VALUE_REQUIRED, 'Only this page URL')
            ->addOption('pageGroup', null, InputOption::VALUE_REQUIRED, 'Only pages of this configured page group')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Only this country (ISO 3166-1 alpha-3, lower case)')
            ->addOption('device', null, InputOption::VALUE_REQUIRED, 'Only desktop, mobile or tablet');
    }

    /** --limit, --offset, --sortBy, --sortDirection */
    protected function addListOptions(): static
    {
        return $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Rows, 1 to 1000', '50')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'First row, 0 to 24999', '0')
            ->addOption('sortBy', null, InputOption::VALUE_REQUIRED, 'clicks, impressions, ctr or position', 'clicks')
            ->addOption('sortDirection', null, InputOption::VALUE_REQUIRED, 'desc or asc (default desc, asc for position)');
    }

    /**
     * Runs as the application's account for CLI operations, then asks the access policy; no rights bypass.
     *
     * @param OutputInterface $output
     * @return Account
     * @throws ForbiddenException when the account may not view site insights (or none is configured)
     */
    protected function authenticateAndRequireViewer(OutputInterface $output): Account
    {
        $defaultAccount = $this->findAccountForCliOperations();
        if ($defaultAccount) {
            AuthService::instance()->setAccount($defaultAccount);
        }
        /** @var SiteInsightsAccessPolicyService $siteInsightsAccessPolicyService */
        $siteInsightsAccessPolicyService = DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
        return $siteInsightsAccessPolicyService->requireViewer();
    }

    /** @return Account|null the account the access policy names for console runs (SiteInsightsAccessPolicyService::findAccountForCliOperations()) */
    protected function findAccountForCliOperations(): ?Account
    {
        /** @var SiteInsightsAccessPolicyService $siteInsightsAccessPolicyService */
        $siteInsightsAccessPolicyService = DDDService::instance()->getService(SiteInsightsAccessPolicyService::class);
        return $siteInsightsAccessPolicyService->findAccountForCliOperations();
    }

    /**
     * @param InputInterface $input
     * @return InsightsSite the site the `site` argument names
     * @throws NotFoundException
     * @throws InternalErrorException
     */
    protected function getSite(InputInterface $input): InsightsSite
    {
        /** @var InsightsSitesService $insightsSitesService */
        $insightsSitesService = DDDService::instance()->getService(InsightsSitesService::class);
        return $insightsSitesService->getSite((string)$input->getArgument('site'));
    }

    /**
     * @param InputInterface $input
     * @return SearchWindowRequest the window the options ask for
     * @throws BadRequestException for a malformed day or day count
     */
    protected function buildWindowRequest(InputInterface $input): SearchWindowRequest
    {
        $searchWindowRequest = new SearchWindowRequest();
        $this->applyWindowOptions($input, $searchWindowRequest);
        return $searchWindowRequest;
    }

    /**
     * @param InputInterface $input
     * @param SearchWindowRequest $searchWindowRequest the request (or a list or export request) the options go into
     * @return void
     * @throws BadRequestException for a malformed day or day count
     */
    protected function applyWindowOptions(InputInterface $input, SearchWindowRequest $searchWindowRequest): void
    {
        $searchWindowRequest->preset = (string)$input->getOption('preset');
        $searchWindowRequest->compare = (string)$input->getOption('compare');
        $searchWindowRequest->searchType = (string)$input->getOption('searchType');
        $searchWindowRequest->dataState = (string)$input->getOption('dataState');
        foreach (['from', 'to'] as $dayOptionName) {
            $dayOptionValue = $input->getOption($dayOptionName);
            if ($dayOptionValue === null) {
                continue;
            }
            if (!SearchDays::isValidDay((string)$dayOptionValue)) {
                throw new BadRequestException('--' . $dayOptionName . ' must be a day written Y-m-d');
            }
            $searchWindowRequest->$dayOptionName = SearchDays::toDate((string)$dayOptionValue);
        }
        $sinceOption = $input->getOption('since');
        if ($sinceOption !== null) {
            if (!ctype_digit((string)$sinceOption) || (int)$sinceOption < 1) {
                throw new BadRequestException('--since must be a number of days');
            }
            if (!isset($searchWindowRequest->to)) {
                throw new BadRequestException('--since needs --to (or use --preset)');
            }
            $searchWindowRequest->from = SearchDays::toDate(SearchDays::addDays(SearchDays::fromDate($searchWindowRequest->to), -((int)$sinceOption - 1)));
        }
    }

    /**
     * @param InputInterface $input
     * @param string $dimension query, page, country or device
     * @return SearchListRequest the window, filter and list options
     * @throws BadRequestException
     */
    protected function buildListRequest(InputInterface $input, string $dimension): SearchListRequest
    {
        $searchListRequest = new SearchListRequest();
        $this->applyWindowOptions($input, $searchListRequest);
        $searchListRequest->dimension = $dimension;
        foreach (['queryContains', 'queryEquals', 'pageContains', 'pageEquals', 'pageGroup', 'country', 'device', 'sortDirection'] as $optionName) {
            $optionValue = $input->getOption($optionName);
            if ($optionValue !== null) {
                $searchListRequest->$optionName = (string)$optionValue;
            }
        }
        $searchListRequest->sortBy = (string)$input->getOption('sortBy');
        foreach (['limit', 'offset'] as $integerOptionName) {
            $optionValue = (string)$input->getOption($integerOptionName);
            if (!ctype_digit($optionValue)) {
                throw new BadRequestException('--' . $integerOptionName . ' must be a whole number');
            }
            $searchListRequest->$integerOptionName = (int)$optionValue;
        }
        return $searchListRequest;
    }

    /**
     * Rows as a table: the dimension value, the metrics and, with a comparison, the counterpart state and deltas;
     * then the window and coverage notes.
     *
     * @param OutputInterface $output
     * @param string $title
     * @param SearchPerformanceRows $searchPerformanceRows
     * @return void
     */
    protected function writeRowsTable(OutputInterface $output, string $title, SearchPerformanceRows $searchPerformanceRows): void
    {
        $output->writeln('<info>' . $title . '</info>');
        if (isset($searchPerformanceRows->error)) {
            $output->writeln('  failed: ' . $searchPerformanceRows->error->cause . ' (' . $searchPerformanceRows->error->message . ')');
            return;
        }
        $dimension = $searchPerformanceRows->dimensions[0] ?? 'date';
        $table = new Table($output);
        $table->setHeaders([$dimension, 'Clicks', 'Impressions', 'CTR', 'Position', 'Counterpart', 'Δ Clicks', 'Δ Position']);
        foreach ($searchPerformanceRows->getElements() as $searchPerformanceRow) {
            $searchMetricsComparison = $searchPerformanceRow->comparison ?? null;
            $table->addRow([
                ($searchPerformanceRow->getDimensionValue($dimension) ?? '') . (($searchPerformanceRow->isFilledDay ?? false) ? ' (no data)' : ''),
                $searchPerformanceRow->metrics->clicks,
                $searchPerformanceRow->metrics->impressions,
                sprintf('%.2f%%', $searchPerformanceRow->metrics->ctr * 100),
                isset($searchPerformanceRow->metrics->position) ? sprintf('%.1f', $searchPerformanceRow->metrics->position) : '',
                $searchMetricsComparison?->counterpartState ?? '',
                $this->formatClicksDelta($searchMetricsComparison),
                isset($searchMetricsComparison->positionDelta) ? sprintf('%+.1f', $searchMetricsComparison->positionDelta) : '',
            ]);
        }
        $table->render();
        foreach ($this->buildCoverageNoteLines($searchPerformanceRows) as $coverageNoteLine) {
            $output->writeln('  ' . $coverageNoteLine);
        }
    }

    /**
     * @param OutputInterface $output
     * @param string $title
     * @param SearchTotals $searchTotals
     * @return void
     */
    protected function writeTotalsLine(OutputInterface $output, string $title, SearchTotals $searchTotals): void
    {
        if (isset($searchTotals->error)) {
            $output->writeln('<info>' . $title . '</info> failed: ' . $searchTotals->error->cause . ' (' . $searchTotals->error->message . ')');
            return;
        }
        $currentMetrics = $searchTotals->metrics->current;
        $output->writeln(sprintf(
            '<info>%s</info> clicks %d, impressions %d, CTR %.2f%%, position %s, %s (%s); comparison: %s %s',
            $title,
            $currentMetrics->clicks,
            $currentMetrics->impressions,
            $currentMetrics->ctr * 100,
            isset($currentMetrics->position) ? sprintf('%.1f', $currentMetrics->position) : '-',
            $currentMetrics->aggregation,
            $currentMetrics->hasData ? 'reported' : 'no row reported',
            $searchTotals->metrics->counterpartState,
            $this->formatClicksDelta($searchTotals->metrics)
        ));
    }

    /**
     * @param SearchWindow $searchWindow
     * @param SearchComparisonWindow|null $searchComparisonWindow
     * @return string the window in provider days, its data state and last final day, and the comparison window
     */
    protected function buildWindowNote(SearchWindow $searchWindow, ?SearchComparisonWindow $searchComparisonWindow): string
    {
        $windowNote = sprintf(
            'Window %s – %s (%d days, %s, dataState %s, final data through %s by %s%s)',
            SearchDays::fromDate($searchWindow->from),
            SearchDays::fromDate($searchWindow->to),
            $searchWindow->days,
            $searchWindow->dayTimeZone,
            $searchWindow->dataState,
            isset($searchWindow->dataThroughDate) ? SearchDays::fromDate($searchWindow->dataThroughDate) : '?',
            $searchWindow->dataThroughDateSource ?? '?',
            $searchWindow->windowClamped ? ', raised to the earliest day kept' : ''
        );
        if ($searchComparisonWindow === null) {
            return $windowNote;
        }
        if (!$searchComparisonWindow->isAvailable()) {
            return $windowNote . '; comparison ' . $searchComparisonWindow->kind . ' refused: ' . $searchComparisonWindow->comparisonUnavailableReason;
        }
        return $windowNote . sprintf(
            '; compared with %s – %s (%s%s)',
            SearchDays::fromDate($searchComparisonWindow->from),
            SearchDays::fromDate($searchComparisonWindow->to),
            $searchComparisonWindow->kind,
            $searchComparisonWindow->comparisonIsPartial ? ', partial: deltas per day' : ''
        );
    }

    /**
     * @param SearchPerformanceRows $searchPerformanceRows
     * @return string[] what the rows cover, one note per line
     */
    protected function buildCoverageNoteLines(SearchPerformanceRows $searchPerformanceRows): array
    {
        $searchRowsCoverage = $searchPerformanceRows->coverage;
        $coverageNoteLines = [];
        if (isset($searchPerformanceRows->window)) {
            $coverageNoteLines[] = $this->buildWindowNote($searchPerformanceRows->window, $searchPerformanceRows->comparisonWindow ?? null);
        }
        $coverageNoteLines[] = sprintf(
            '%d rows fetched (%d per call), %s, sorted by %s, aggregation %s%s, %d provider calls%s',
            $searchRowsCoverage->fetchedRows,
            $searchRowsCoverage->rowLimitPerCall,
            $searchRowsCoverage->limitReached ? 'more rows may exist (limit reached)' : ($searchRowsCoverage->providerRowsExhausted ? 'the provider returned fewer rows than asked' : 'page full'),
            $searchRowsCoverage->sortScope === SearchRowsCoverage::SORT_SCOPE_PROVIDER ? 'the provider' : 'the module over the fetched rows',
            $searchRowsCoverage->responseAggregation ?? '?',
            $searchRowsCoverage->responseAggregationKnown ? '' : ' (not named by the provider)',
            $searchRowsCoverage->providerCalls,
            $searchRowsCoverage->fromCache ? ', from the cache' : ''
        );
        if ($searchRowsCoverage->anonymizedQueriesOmitted) {
            $coverageNoteLines[] = 'Anonymised queries are omitted: sums of these rows are below the totals.';
        }
        if ($searchRowsCoverage->joinedDimensionsMayLoseRows) {
            $coverageNoteLines[] = 'Query × page: the provider drops rows in joint reports.';
        }
        if ($searchRowsCoverage->duplicateRowsDropped > 0) {
            $coverageNoteLines[] = $searchRowsCoverage->duplicateRowsDropped . ' rows repeated across pages were dropped.';
        }
        if ($searchRowsCoverage->counterpartsNotRequested > 0) {
            $coverageNoteLines[] = $searchRowsCoverage->counterpartsNotRequested . ' rows have no counterpart asked (counterpart limits).';
        }
        return $coverageNoteLines;
    }

    /**
     * @param IndexingCoverage $indexingCoverage
     * @return string what an indexing answer covers, in one line
     */
    protected function buildIndexingCoverageNote(IndexingCoverage $indexingCoverage): string
    {
        return sprintf(
            '%d part(s) asked, %d from the cache, %d failed, %d provider call(s)%s; answers are kept %d s',
            $indexingCoverage->requestedParts,
            $indexingCoverage->partsFromCache,
            $indexingCoverage->failedParts,
            $indexingCoverage->providerCalls,
            isset($indexingCoverage->fetchedAt) ? ', answered ' . gmdate('Y-m-d H:i', $indexingCoverage->fetchedAt->getTimestamp()) . ' UTC' : '',
            $indexingCoverage->cacheLifetimeSeconds
        );
    }

    /**
     * @param SearchMetricsComparison|null $searchMetricsComparison
     * @return string the clicks delta (absolute, or per day for a partial comparison), empty without one
     */
    protected function formatClicksDelta(?SearchMetricsComparison $searchMetricsComparison): string
    {
        if (isset($searchMetricsComparison->clicksDelta)) {
            return sprintf('%+d', $searchMetricsComparison->clicksDelta);
        }
        if (isset($searchMetricsComparison->clicksPerDayDelta)) {
            return sprintf('%+.1f/day', $searchMetricsComparison->clicksPerDayDelta);
        }
        return '';
    }

    /**
     * @param SearchPerformanceRows|SearchTotals ...$answerParts
     * @return int 1 when a part failed (the payload is printed all the same), else 0
     */
    protected function determineExitCodeOfParts(SearchPerformanceRows|SearchTotals ...$answerParts): int
    {
        foreach ($answerParts as $answerPart) {
            if (isset($answerPart->error)) {
                return self::EXIT_OPERATIONAL_FAILURE;
            }
        }
        return self::EXIT_SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @return string table, json or toon
     * @throws BadRequestException
     */
    protected function getFormat(InputInterface $input): string
    {
        $format = (string)$input->getOption('format');
        if (!in_array($format, self::FORMATS, true)) {
            throw new BadRequestException('--format must be one of ' . implode(', ', self::FORMATS));
        }
        return $format;
    }

    /**
     * Writes the payload in the chosen format: JSON = `{propertyName: payload}` as the admin endpoint returns it,
     * compact unless --pretty; TOON = the same envelope with every row set in TOON.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $propertyName
     * @param object $payload a framework object
     * @return void
     */
    protected function writePayload(InputInterface $input, OutputInterface $output, string $propertyName, object $payload): void
    {
        if ($input->getOption('format') === self::FORMAT_TOON) {
            $this->writeToonPayload($output, $propertyName, $payload);
            return;
        }
        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($input->getOption('pretty') ? JSON_PRETTY_PRINT : 0);
        /** @var SerializerTrait $payload */
        $output->writeln((string)json_encode([$propertyName => $payload->toObject(cached: false, forPersistence: false)], $jsonFlags));
    }

    /**
     * The JSON envelope with every row set (an ObjectSet: the payload or one of its properties) serialised as TOON
     * (`elementsInToonFormat`); the metadata around the rows stays JSON.
     *
     * @param OutputInterface $output
     * @param string $propertyName
     * @param object $payload
     * @return void
     */
    protected function writeToonPayload(OutputInterface $output, string $propertyName, object $payload): void
    {
        $this->markRowSetsAsToon($payload);
        /** @var SerializerTrait $payload */
        $output->writeln((string)json_encode(
            [$propertyName => $payload->toObject(cached: false, forPersistence: false)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ));
    }

    /**
     * @param object $payload
     * @return void
     */
    protected function markRowSetsAsToon(object $payload): void
    {
        if ($payload instanceof ObjectSet) {
            $payload->addPropertiesToSerializeAsToon('elements');
        }
        foreach (get_object_vars($payload) as $propertyValue) {
            if ($propertyValue instanceof ObjectSet) {
                $propertyValue->addPropertiesToSerializeAsToon('elements');
            }
        }
    }

    /**
     * Runs `$run` and turns the module's errors into exit codes: invalid input (400, 404) → 2; forbidden, provider,
     * budget and configuration errors → 1. Diagnostics go to stderr; stdout stays the payload.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param callable $run returns the exit code
     * @return int
     */
    protected function runGuarded(InputInterface $input, OutputInterface $output, callable $run): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        try {
            return (int)$run();
        } catch (BadRequestException|NotFoundException $invalidInputException) {
            $errorOutput->writeln('<error>' . $invalidInputException->getMessage() . '</error>');
            return self::EXIT_INVALID_INPUT;
        } catch (SiteInsightsProviderUnavailableException|SiteInsightsBudgetExceededException $operationalException) {
            $errorOutput->writeln('<error>' . $operationalException->cause . ': ' . $operationalException->getMessage() . '</error>');
            return self::EXIT_OPERATIONAL_FAILURE;
        } catch (ForbiddenException|InternalErrorException $operationalException) {
            $errorOutput->writeln('<error>' . $operationalException->getMessage() . '</error>');
            return self::EXIT_OPERATIONAL_FAILURE;
        }
    }
}
