<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;
use DDD\Infrastructure\Base\DateTime\DateTime;

/**
 * Up to which day a provider's data is there, read with a probe over the last ten days (Google names no last final
 * day; a question by date is its documented way).
 */
class SearchDataAvailability extends ValueObject
{
    /** @var string Search type, e.g. web */
    public string $searchType;

    /** @var string final or all */
    public string $dataState;

    /** @var Date The last day with data of this state */
    public Date $dataThroughDate;

    /** @var string probe (read from the provider) or fallback (today − 3 in the provider's zone) */
    public string $dataThroughDateSource;

    /** @var Date|null The first incomplete day (dataState all) */
    public ?Date $firstIncompleteDate;

    /** @var DateTime|null When the provider answered the probe */
    public ?DateTime $fetchedAt;

    /** @var SearchPartError|null Why the probe failed (then the fallback applies) */
    public ?SearchPartError $error;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
