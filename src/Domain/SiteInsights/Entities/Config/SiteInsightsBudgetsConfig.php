<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * `SiteInsights.budgets`: shares of Google's quotas, row and size limits, timeouts, retention, comparison limits and
 * the provider calls each operation may plan. A setting the application's file leaves out keeps the default below.
 */
class SiteInsightsBudgetsConfig extends ValueObject
{
    public const string OPERATION_OVERVIEW = 'overview';
    public const string OPERATION_LIST = 'list';
    public const string OPERATION_PAGE_GROUPS = 'pageGroups';
    public const string OPERATION_PAGE_DETAIL = 'pageDetail';
    public const string OPERATION_QUERY_DETAIL = 'queryDetail';
    public const string OPERATION_OPPORTUNITIES = 'opportunities';
    public const string OPERATION_SITEMAPS = 'sitemaps';
    public const string OPERATION_INSPECTIONS = 'inspections';
    public const string OPERATION_EXPORT = 'export';
    public const string OPERATION_CHECK_ACCESS = 'checkAccess';

    /** @var int Google's Search Analytics requests per minute and site */
    public int $searchAnalyticsPerMinutePerSite = 1200;

    /** @var float Share of it the module uses */
    public float $searchAnalyticsShare = 0.5;

    /** @var int Google's URL inspections per day and site */
    public int $inspectionsPerDayPerSite = 2000;

    /** @var int Google's URL inspections per minute and site */
    public int $inspectionsPerMinutePerSite = 600;

    /** @var float Share of the inspection quotas the module uses */
    public float $inspectionShare = 0.5;

    /** @var int Google's calls per minute of the other resources */
    public int $otherPerMinute = 200;

    /** @var float Share of it the module uses */
    public float $otherShare = 0.5;

    /** @var int Rows asked per call of a list that pages */
    public int $rowsPerCall = 2500;

    /** @var int Most rows one call may ask (export pages) */
    public int $maxRowsPerCall = 10000;

    /** @var int Most rows a list fetches before the module sorts them itself */
    public int $sortFetchCeiling = 5000;

    /** @var int Bytes one private answer may have */
    public int $maxResponseBytes = 4000000;

    /** @var int Timeout of a batch of calls */
    public int $batchTimeoutSeconds = 25;

    /** @var int Timeout of a single call */
    public int $singleCallTimeoutSeconds = 20;

    /** @var int Months the provider keeps */
    public int $retentionMonths = 16;

    /** @var int Days added to the earliest kept day as a margin */
    public int $retentionSafetyDays = 3;

    /** @var int Fewest days of a partial comparison */
    public int $comparisonMinDays = 7;

    /** @var float Smallest share of the window a partial comparison must cover */
    public float $comparisonMinShare = 0.25;

    /** @var int[] Provider calls each operation may plan, by operation name */
    public array $callsPerOperation = [
        self::OPERATION_OVERVIEW => 10,
        self::OPERATION_LIST => 8,
        self::OPERATION_PAGE_GROUPS => 41,
        self::OPERATION_PAGE_DETAIL => 7,
        self::OPERATION_QUERY_DETAIL => 7,
        self::OPERATION_OPPORTUNITIES => 20,
        self::OPERATION_SITEMAPS => 1,
        self::OPERATION_INSPECTIONS => 20,
        self::OPERATION_EXPORT => 1500,
        self::OPERATION_CHECK_ACCESS => 6,
    ];

    /** @var int Most keys of one counterpart call */
    public int $counterpartKeysPerChunk = 100;

    /** @var int Most counterpart calls of one list */
    public int $counterpartMaxChunks = 5;

    /** @var int Longest regular expression of one counterpart call (Google's limit is not documented) */
    public int $counterpartRegexMaxLength = 4000;

    /** @var int Most page groups asked at once */
    public int $maxPageGroups = 20;

    /** @var int Most URLs of one inspection request */
    public int $maxInspectionUrls = 20;

    /** @var int How often an export asks a page again after a retryable failure before it ends */
    public int $exportRetries = 2;

    /** @var int Wait before an export retry, times the retry's number (1 s, 2 s, …) */
    public int $exportRetryBackoffMilliseconds = 1000;

    /** @var int Rows of each top list of the overview */
    public int $overviewListSize = 25;

    /** @var int Rows of the lists of a page or query detail */
    public int $detailListSize = 50;

    /**
     * @param array $configuredSettings the file `SiteInsights.budgets` as configured (wrapped here, never passed on)
     * @return static the settings over the defaults
     */
    public static function fromConfigSettings(array $configuredSettings): static
    {
        $budgetsConfig = new static();
        foreach (['searchAnalyticsPerMinutePerSite', 'inspectionsPerDayPerSite', 'inspectionsPerMinutePerSite', 'otherPerMinute', 'rowsPerCall', 'maxRowsPerCall', 'sortFetchCeiling', 'maxResponseBytes', 'batchTimeoutSeconds', 'singleCallTimeoutSeconds', 'maxPageGroups', 'maxInspectionUrls', 'overviewListSize', 'detailListSize', 'exportRetries', 'exportRetryBackoffMilliseconds'] as $integerSettingName) {
            if (isset($configuredSettings[$integerSettingName])) {
                $budgetsConfig->$integerSettingName = (int)$configuredSettings[$integerSettingName];
            }
        }
        foreach (['searchAnalyticsShare', 'inspectionShare', 'otherShare'] as $shareSettingName) {
            if (isset($configuredSettings[$shareSettingName])) {
                $budgetsConfig->$shareSettingName = (float)$configuredSettings[$shareSettingName];
            }
        }
        $retentionSettings = (array)($configuredSettings['retention'] ?? []);
        $budgetsConfig->retentionMonths = (int)($retentionSettings['months'] ?? $budgetsConfig->retentionMonths);
        $budgetsConfig->retentionSafetyDays = (int)($retentionSettings['safetyDays'] ?? $budgetsConfig->retentionSafetyDays);
        $comparisonSettings = (array)($configuredSettings['comparison'] ?? []);
        $budgetsConfig->comparisonMinDays = (int)($comparisonSettings['minDays'] ?? $budgetsConfig->comparisonMinDays);
        $budgetsConfig->comparisonMinShare = (float)($comparisonSettings['minShare'] ?? $budgetsConfig->comparisonMinShare);
        $counterpartSettings = (array)($configuredSettings['counterpart'] ?? []);
        $budgetsConfig->counterpartKeysPerChunk = (int)($counterpartSettings['keysPerChunk'] ?? $budgetsConfig->counterpartKeysPerChunk);
        $budgetsConfig->counterpartMaxChunks = (int)($counterpartSettings['maxChunks'] ?? $budgetsConfig->counterpartMaxChunks);
        $budgetsConfig->counterpartRegexMaxLength = (int)($counterpartSettings['regexMaxLength'] ?? $budgetsConfig->counterpartRegexMaxLength);
        foreach ((array)($configuredSettings['callsPerOperation'] ?? []) as $operation => $calls) {
            $budgetsConfig->callsPerOperation[(string)$operation] = (int)$calls;
        }
        return $budgetsConfig;
    }

    /**
     * @param string $operation
     * @return int|null the provider calls the operation may plan; null for an unknown operation
     */
    public function getCallsAllowedForOperation(string $operation): ?int
    {
        return $this->callsPerOperation[$operation] ?? null;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
