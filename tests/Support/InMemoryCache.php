<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Support;

use DDD\Infrastructure\Cache\Apc;
use DDD\Infrastructure\Cache\Cache;
use LogicException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * A cache group in memory that records the TTL of every write and keeps atomic counters apart from values, like the
 * real backends. {@see installForCacheGroups()} puts one instance under the APC and one under the Redis Sentinel group.
 */
class InMemoryCache extends Apc
{
    /** @var array Values by key */
    public array $valuesByKey = [];

    /** @var int[] The TTL each key was last written with */
    public array $ttlsByKey = [];

    /** @var int[] Counters by key */
    public array $countersByKey = [];

    /** @var int[] The TTL each counter was created with */
    public array $counterTtlsByKey = [];

    /** @return InMemoryCache[] the installed caches by group */
    public static function installForCacheGroups(): array
    {
        $installedCaches = [
            Cache::CACHE_GROUP_APC => new self(['defaultTTL' => 60]),
            Cache::CACHE_GROUP_REDIS_SENTINEL => new self(['defaultTTL' => 60]),
        ];
        static::$instances = $installedCaches + (static::$instances ?? []);
        return $installedCaches;
    }

    public static function uninstall(): void
    {
        unset(static::$instances[Cache::CACHE_GROUP_APC], static::$instances[Cache::CACHE_GROUP_REDIS_SENTINEL]);
    }

    public function get(string ...$keys): mixed
    {
        if (count($keys) > 1) {
            $values = [];
            foreach ($keys as $key) {
                if (array_key_exists($key, $this->valuesByKey)) {
                    $values[$key] = $this->valuesByKey[$key];
                }
            }
            return $values;
        }
        return $this->valuesByKey[$keys[0]] ?? false;
    }

    public function set(string $key, mixed $value, ?int $ttl = null, bool $deferred = false): void
    {
        $this->valuesByKey[$key] = $value;
        $this->ttlsByKey[$key] = $ttl;
    }

    public function delete(string $key): void
    {
        unset($this->valuesByKey[$key], $this->ttlsByKey[$key]);
    }

    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        if (!isset($this->countersByKey[$key])) {
            $this->countersByKey[$key] = 0;
            $this->counterTtlsByKey[$key] = $ttl ?? $this->ttl;
        }
        $this->countersByKey[$key] += $by;
        return $this->countersByKey[$key];
    }

    public function decrement(string $key, int $by = 1): int
    {
        $this->countersByKey[$key] = ($this->countersByKey[$key] ?? 0) - $by;
        return $this->countersByKey[$key];
    }

    public function getCounter(string $key): int
    {
        return $this->countersByKey[$key] ?? 0;
    }

    public function deleteCounter(string $key): void
    {
        unset($this->countersByKey[$key], $this->counterTtlsByKey[$key]);
    }

    public function getCacheAdapter(): ApcuAdapter
    {
        throw new LogicException('The in-memory test cache has no adapter');
    }

    /** @return string every stored value and counter key as one string (for secret greps) */
    public function dumpAsText(): string
    {
        return (string)json_encode([$this->valuesByKey, array_keys($this->countersByKey)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
