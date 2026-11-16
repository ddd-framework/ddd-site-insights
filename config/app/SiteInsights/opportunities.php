<?php

declare(strict_types=1);

/**
 * Thresholds of the opportunity rules (evaluated from version 0.4 on). Thresholds in impressions or clicks are per 28
 * days and scaled to the window. An application replaces this file as a whole.
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
        'minImpressions' => 200,
        'minRowsPerBucket' => 8,
        'ratio' => 0.5,
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
