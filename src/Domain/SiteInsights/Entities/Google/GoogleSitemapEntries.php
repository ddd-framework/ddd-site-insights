<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The sitemap files of a property.
 *
 * @property GoogleSitemapEntry[] $elements
 * @method GoogleSitemapEntry[] getElements()
 * @method GoogleSitemapEntry|null first()
 */
class GoogleSitemapEntries extends ObjectSet
{
}
