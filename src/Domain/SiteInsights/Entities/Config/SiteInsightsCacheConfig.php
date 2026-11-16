<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;

/** `SiteInsights.cache`: lifetimes of provider answers. A setting the application's file leaves out keeps the default. */
class SiteInsightsCacheConfig extends ValueObject
{
    /** @var int Raised to make every cached answer unreachable after a change of the module's question shape */
    public int $schemaVersion = 1;

    /** @var int dataState final with a window ending on or before the last final day */
    public int $finalWindowTtlSeconds = 86400;

    /** @var int dataState all, or a window reaching beyond the last final day */
    public int $freshWindowTtlSeconds = 1800;

    /** @var int The availability probe */
    public int $probeTtlSeconds = 3600;

    /** @var int Sitemaps */
    public int $sitemapsTtlSeconds = 3600;

    /** @var int URL inspections */
    public int $inspectionTtlSeconds = 86400;

    /** @var int How long a request waits for the same question another process is already asking */
    public int $joinWaitMilliseconds = 10000;

    /**
     * @param array $configuredSettings the file `SiteInsights.cache` as configured (wrapped here, never passed on)
     * @return static
     */
    public static function fromConfigSettings(array $configuredSettings): static
    {
        $cacheConfig = new static();
        foreach (['schemaVersion', 'finalWindowTtlSeconds', 'freshWindowTtlSeconds', 'probeTtlSeconds', 'sitemapsTtlSeconds', 'inspectionTtlSeconds', 'joinWaitMilliseconds'] as $settingName) {
            if (isset($configuredSettings[$settingName])) {
                $cacheConfig->$settingName = (int)$configuredSettings[$settingName];
            }
        }
        return $cacheConfig;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
