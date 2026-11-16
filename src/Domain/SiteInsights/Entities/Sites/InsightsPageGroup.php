<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Sites;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * A configured group of pages of a site, e.g. all guide pages. Groups may overlap; their totals are never summed.
 */
class InsightsPageGroup extends ValueObject
{
    /** @var string Key of the group within its site */
    public string $key;

    /** @var string Label shown in the admin */
    public string $label;

    /** @var string[] RE2 patterns on the URL path (no lookarounds, no backreferences) */
    public array $pathPatterns = [];

    /**
     * @param string $urlPath
     * @return bool whether a pattern of the group matches the path
     */
    public function matchesPath(string $urlPath): bool
    {
        foreach ($this->pathPatterns as $pathPattern) {
            if (preg_match('~' . str_replace('~', '\\~', $pathPattern) . '~u', $urlPath) === 1) {
                return true;
            }
        }
        return false;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->key ?? '');
    }
}
