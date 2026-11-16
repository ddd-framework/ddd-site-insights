<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * One content type of a sitemap file (`sitemap[].contents[]`). Google's `indexed` count is deprecated by Google
 * ("do not use") and is not read.
 */
class GoogleSitemapContent extends ValueObject
{
    /** @var string Google's `type` (web, image, video, news, mobile, androidApp, pattern, iosApp, …) */
    public string $type;

    /** @var int URLs of this type submitted in the file (an int64 string in Google's JSON) */
    public int $submitted = 0;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic($this->type ?? '');
    }
}
