<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Access;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * The numbers and names an access check's detail is built from, so a translated host words the line itself. Only the
 * fields of the line's detailCode are set.
 */
class SiteInsightsAccessCheckValues extends ValueObject
{
    /** @var int|null `rowsRead`: rows the one-row Search Analytics call returned */
    public ?int $rows;

    /** @var int|null `sitemapsListed`: sitemap files in the list */
    public ?int $files;

    /** @var string|null `inspectionVerdict`: the verdict of the inspected root URL (e.g. PASS), or unknown */
    public ?string $verdict;

    /** @var string|null `permissionLevel`: the provider's permission level name, or unknown */
    public ?string $level;

    /** @var int|null `propertyNotListed`: properties the credentials entry lists */
    public ?int $listedProperties;

    public static function forRows(int $rows): static
    {
        $siteInsightsAccessCheckValues = new static();
        $siteInsightsAccessCheckValues->rows = $rows;
        return $siteInsightsAccessCheckValues;
    }

    public static function forFiles(int $files): static
    {
        $siteInsightsAccessCheckValues = new static();
        $siteInsightsAccessCheckValues->files = $files;
        return $siteInsightsAccessCheckValues;
    }

    public static function forVerdict(string $verdict): static
    {
        $siteInsightsAccessCheckValues = new static();
        $siteInsightsAccessCheckValues->verdict = $verdict;
        return $siteInsightsAccessCheckValues;
    }

    public static function forLevel(string $level): static
    {
        $siteInsightsAccessCheckValues = new static();
        $siteInsightsAccessCheckValues->level = $level;
        return $siteInsightsAccessCheckValues;
    }

    public static function forListedProperties(int $listedProperties): static
    {
        $siteInsightsAccessCheckValues = new static();
        $siteInsightsAccessCheckValues->listedProperties = $listedProperties;
        return $siteInsightsAccessCheckValues;
    }
}
