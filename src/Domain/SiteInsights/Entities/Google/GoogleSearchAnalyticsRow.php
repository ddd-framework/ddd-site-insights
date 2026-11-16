<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Google;

use DDD\Domain\Base\Entities\ValueObject;

/** One row of Google's answer: `keys` in the order of the question's dimensions, metrics as Google sends them */
class GoogleSearchAnalyticsRow extends ValueObject
{
    /** @var string[] Google's `keys` */
    public array $keys = [];

    /** @var float Google's `clicks` (a JSON number) */
    public float $clicks;

    /** @var float Google's `impressions` */
    public float $impressions;

    /** @var float Google's `ctr`, 0–1 */
    public float $ctr;

    /** @var float|null Average position; unset for search types without position (discover, googleNews) */
    public ?float $position;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic(hash('sha256', (string)json_encode($this->keys, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
    }
}
