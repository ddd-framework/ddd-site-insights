<?php

declare(strict_types=1);

namespace DDD\Modules\SiteInsights;

use DDD\Infrastructure\Modules\DDDModule;

/**
 * Site insights: reads Google Search Console through ddd-argus, caches the answers, stores nothing and states in every
 * answer what it covers. Sites, credentials, cache lifetimes and call budgets are configuration under
 * `config/app/SiteInsights/`; an application replaces a file of it as a whole.
 */
class SiteInsightsModule extends DDDModule
{
    public static function getSourcePath(): string
    {
        return __DIR__ . '/../..';
    }

    public static function getConfigPath(): ?string
    {
        return __DIR__ . '/../../../config/app';
    }

    public static function getPublicServiceNamespaces(): array
    {
        return [
            'DDD\\Domain\\SiteInsights\\Services\\',
        ];
    }

    /**
     * The framework's exclusions plus `Credentials` (holders of a service-account key or an access token: never a
     * service, never serialised) and `Utils` (static helpers).
     *
     * @return string[]
     */
    public static function getExcludePatterns(): array
    {
        return [...parent::getExcludePatterns(), 'Credentials', 'Utils'];
    }
}
