<?php

declare(strict_types=1);

/**
 * The sites the module reads. An application replaces this file as a whole (config/app/SiteInsights/sites.php).
 *
 * Each entry of `sites`:
 * - `key`: `^[a-z0-9][a-z0-9-]{0,62}$`, used in URLs and commands
 * - `label`: shown in the admin
 * - `provider`: `google-search-console`
 * - `property`: the Search Console property, `https://example.test/` (URL prefix) or `sc-domain:example.test`
 * - `credentials`: the name of an entry of SiteInsights.credentials.entries
 * - `brandQueryPatterns`: RE2 patterns of brand queries (no lookarounds, no backreferences)
 * - `pageGroups`: list of `['key' => …, 'label' => …, 'pathPatterns' => [RE2 on the URL path, …]]`
 *
 * Example:
 *
 *     'sites' => [
 *         [
 *             'key' => 'example-site',
 *             'label' => 'Example site',
 *             'provider' => 'google-search-console',
 *             'property' => 'https://example.test/',
 *             'credentials' => 'searchReader',
 *             'brandQueryPatterns' => ['example'],
 *             'pageGroups' => [
 *                 ['key' => 'guides', 'label' => 'Guides', 'pathPatterns' => ['^/guides/']],
 *             ],
 *         ],
 *     ],
 */
return [
    'sites' => [],
];
