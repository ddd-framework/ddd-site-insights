<?php

declare(strict_types=1);

/**
 * Thresholds of the opportunity rules. Thresholds in impressions or clicks are per 28 days and scaled to the window
 * (ceil(value × days / 28)). An application replaces this file as a whole; a site overrides single settings with its
 * own `opportunities` entry in sites.php (same shape). A setting left out keeps the module's default.
 */
return [
    'minWindowDays' => 7,
    'strikingDistanceQuery' => [
        'positionFrom' => 8.0,
        'positionTo' => 20.0,
        'minImpressions' => 100,
        'excludeBrand' => true,
        'maxResults' => 50,
    ],
    'weakClickRateForPosition' => [
        'minImpressions' => 50,
        'minRowsPerBucket' => 5,
        // a baseline below 0.5 % click rate (zero-click rows dominate the bucket) judges no row
        'minBaselineClickRate' => 0.005,
        'ratio' => 0.5,
        'excludeBrand' => true,
        // a device bucket below minRowsPerBucket takes the baseline of all devices (the result says so: baselineScope)
        'fallbackToAllDevices' => true,
        'maxResults' => 50,
    ],
    'severalPagesForQuery' => [
        'topQueries' => 50,
        'pagesPerQueryMax' => 10,
        'minImpressionsPerPage' => 20,
        'minShare' => 0.1,
        'minPages' => 2,
    ],
    'risingAndDeclining' => [
        'candidatesPerPeriod' => 100,
        'minClicksEitherPeriod' => 10,
        'minAbsoluteDelta' => 5,
        'minRelativeDelta' => 0.3,
        'maxResults' => 25,
    ],
];
