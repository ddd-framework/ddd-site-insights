<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * The content types of a sitemap file.
 *
 * @property GoogleSitemapContent[] $elements
 * @method GoogleSitemapContent[] getElements()
 * @method GoogleSitemapContent|null first()
 */
class GoogleSitemapContents extends ObjectSet
{
}
