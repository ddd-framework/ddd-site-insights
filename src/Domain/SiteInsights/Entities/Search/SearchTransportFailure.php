<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * What the module knows about a call that got no answer at all (cause `transport`). The private transport hides the
 * HTTP client's exception on purpose (it may carry request data), so connection, DNS, reset and stalled transfer
 * cannot be told apart; the module measures how long the call took against its timeout instead.
 */
class SearchTransportFailure extends ValueObject
{
    /** The call ran into its timeout */
    public const string KIND_TIMEOUT = 'timeout';

    /** The call ended before its timeout: a connection, DNS or reset error, or a stalled transfer (not told apart) */
    public const string KIND_ENDED_BEFORE_TIMEOUT = 'endedBeforeTimeout';

    /** @var float Share of the timeout from which a failed call counts as timed out */
    public const float TIMEOUT_SHARE = 0.95;

    /** @var string timeout or endedBeforeTimeout */
    public string $kind;

    /** @var int How long the failed call round took */
    public int $elapsedMilliseconds = 0;

    /** @var int The timeout of the call (budgets.batchTimeoutSeconds) */
    public int $timeoutSeconds = 0;

    /**
     * @param int $elapsedMilliseconds
     * @param int $timeoutSeconds
     * @return static
     */
    public static function create(int $elapsedMilliseconds, int $timeoutSeconds): static
    {
        $searchTransportFailure = new static();
        $searchTransportFailure->elapsedMilliseconds = max(0, $elapsedMilliseconds);
        $searchTransportFailure->timeoutSeconds = $timeoutSeconds;
        $searchTransportFailure->kind = $timeoutSeconds > 0 && $elapsedMilliseconds >= $timeoutSeconds * 1000 * self::TIMEOUT_SHARE
            ? self::KIND_TIMEOUT
            : self::KIND_ENDED_BEFORE_TIMEOUT;
        return $searchTransportFailure;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
