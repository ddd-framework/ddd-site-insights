<?php

declare(strict_types=1);

/**
 * Cache lifetimes of provider answers. An application replaces this file as a whole.
 */
return [
    // raised to make every cached answer unreachable after a change of the module's question shape
    'schemaVersion' => 1,
    // dataState final with a window that ends on or before the last final day
    'finalWindowTtlSeconds' => 86400,
    // dataState all, or a window that reaches beyond the last final day
    'freshWindowTtlSeconds' => 1800,
    'probeTtlSeconds' => 3600,
    'sitemapsTtlSeconds' => 3600,
    'inspectionTtlSeconds' => 86400,
    // how long a request waits for the same question another process is already asking
    'joinWaitMilliseconds' => 10000,
];
