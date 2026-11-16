<?php

declare(strict_types=1);

/**
 * Credentials of the provider calls. An application replaces this file as a whole.
 *
 * `entries`: name => ['envName' => the environment variable holding the service-account JSON key, base64 in one
 * line; 'revision' => int, raised when the account behind the entry changes (older cache entries become unreachable)].
 * The key itself never appears in configuration, logs or caches; only its name does.
 */
return [
    'entries' => [],
    'tokenEndpoint' => 'https://oauth2.googleapis.com/token',
    // must end in `.readonly`
    'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
    // Google accepts at most 3600
    'assertionLifetimeSeconds' => 300,
    // a token is cached for its lifetime minus this margin; below 60 s it is not cached
    'tokenSafetyMarginSeconds' => 300,
    'httpTimeoutSeconds' => 10,
];
