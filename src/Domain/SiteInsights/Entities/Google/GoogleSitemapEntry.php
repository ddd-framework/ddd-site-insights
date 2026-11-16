<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;

/** One sitemap file of Google's sitemaps list (`sitemap[]`) */
class GoogleSitemapEntry extends ValueObject
{
    /** @var string URL of the sitemap file */
    public string $path;

    /** @var string|null Google's `type` (sitemap, rssFeed, atomFeed, …) */
    public ?string $type;

    /** @var bool Whether Google has not processed the file yet */
    public bool $isPending = false;

    /** @var bool Whether the file is a sitemap index */
    public bool $isSitemapsIndex = false;

    /** @var int Warnings Google reported (an int64 string in Google's JSON) */
    public int $warnings = 0;

    /** @var int Errors Google reported (an int64 string in Google's JSON) */
    public int $errors = 0;

    /** @var DateTime|null When the file was last submitted */
    public ?DateTime $lastSubmitted;

    /** @var DateTime|null When Google last downloaded the file */
    public ?DateTime $lastDownloaded;

    /** @var GoogleSitemapContents|null Google's `contents`: URLs submitted per content type; unset when Google sent none */
    public ?GoogleSitemapContents $contents;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', $this->path ?? ''));
    }
}
