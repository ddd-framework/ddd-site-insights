<?php

declare(strict_types=1);

/**
 * Provider call budgets, row and size limits. An application replaces this file as a whole.
 */
return [
    // Google: 1,200 Search Analytics requests per minute per site; the module uses the share of it
    'searchAnalyticsPerMinutePerSite' => 1200,
    'searchAnalyticsShare' => 0.5,
    // Google: URL inspection 2,000 per day and 600 per minute per site
    'inspectionsPerDayPerSite' => 2000,
    'inspectionsPerMinutePerSite' => 600,
    'inspectionShare' => 0.5,
    // Google: other resources 200 per minute
    'otherPerMinute' => 200,
    'otherShare' => 0.5,
    'rowsPerCall' => 2500,
    'maxRowsPerCall' => 10000,
    'sortFetchCeiling' => 5000,
    // bytes one private answer may have (Argus allows at most 4,194,304)
    'maxResponseBytes' => 4000000,
    'batchTimeoutSeconds' => 25,
    'singleCallTimeoutSeconds' => 20,
    'retention' => ['months' => 16, 'safetyDays' => 3],
    'comparison' => ['minDays' => 7, 'minShare' => 0.25],
    // the most provider calls one operation may plan (retries included)
    'callsPerOperation' => [
        'overview' => 10,
        'list' => 8,
        'pageGroups' => 41,
        'pageDetail' => 7,
        'queryDetail' => 7,
        'opportunities' => 20,
        'sitemaps' => 1,
        'inspections' => 20,
        'export' => 1500,
        'checkAccess' => 6,
    ],
    'counterpart' => ['keysPerChunk' => 100, 'maxChunks' => 5, 'regexMaxLength' => 4000],
    'maxPageGroups' => 20,
    'maxInspectionUrls' => 20,
];
