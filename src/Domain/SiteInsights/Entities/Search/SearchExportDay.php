<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

/** What one window of an export (one day, or the whole window when not asked by day) covered */
class SearchExportDay extends ValueObject
{
    /** @var Date First day of the part */
    public Date $from;

    /** @var Date Last day of the part */
    public Date $to;

    /** @var int Rows written */
    public int $fetchedRows = 0;

    /** @var int Provider calls made */
    public int $providerCalls = 0;

    /** @var int Calls asked again after a retryable failure (budgets.exportRetries per page) */
    public int $retries = 0;

    /** @var bool The last page returned fewer rows than asked (not "all rows": the provider keeps top rows only) */
    public bool $providerRowsExhausted = false;

    /** @var bool The export stopped in this part (call budget) while the last page was full */
    public bool $limitReached = false;

    /** @var SearchPartError|null Why this part failed (the export stops there) */
    public ?SearchPartError $error;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
