<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Utils;

use DateTimeImmutable;
use DateTimeZone;
use DDD\Infrastructure\Base\DateTime\Date;
use DDD\Infrastructure\Exceptions\BadRequestException;

/**
 * Day arithmetic of the module. Days are `Y-m-d` strings in the provider's day zone; every computation runs on
 * `DateTimeImmutable` in UTC, so neither PHP's default time zone nor a shared `Date` instance can shift a day. A
 * framework `Date` is only created at the end ({@see toDate()}) and never modified.
 */
class SearchDays
{
    public const string DAY_FORMAT = 'Y-m-d';

    /**
     * @param string $zone e.g. America/Los_Angeles
     * @param int|null $timestamp the moment (default now)
     * @return string the day in that zone at that moment
     */
    public static function todayInZone(string $zone, ?int $timestamp = null): string
    {
        $moment = (new DateTimeImmutable('@' . ($timestamp ?? time())))->setTimezone(new DateTimeZone($zone));
        return $moment->format(self::DAY_FORMAT);
    }

    /**
     * @param string $ymd
     * @param int $days negative to go back
     * @return string
     */
    public static function addDays(string $ymd, int $days): string
    {
        return self::parseDay($ymd)->modify(($days >= 0 ? '+' : '') . $days . ' days')->format(self::DAY_FORMAT);
    }

    /**
     * The same day of the month `$months` months earlier; a day the target month does not have is clamped to its last
     * day (2026-08-31 minus 6 months is 2026-02-28).
     *
     * @param string $ymd
     * @param int $months
     * @return string
     */
    public static function subtractMonthsClamped(string $ymd, int $months): string
    {
        $day = self::parseDay($ymd);
        $year = (int)$day->format('Y');
        $month = (int)$day->format('n') - $months;
        $dayOfMonth = (int)$day->format('j');
        while ($month < 1) {
            $month += 12;
            $year--;
        }
        $firstOfTargetMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC'));
        $daysInTargetMonth = (int)$firstOfTargetMonth->format('t');
        return sprintf('%04d-%02d-%02d', $year, $month, min($dayOfMonth, $daysInTargetMonth));
    }

    /**
     * @param string $from
     * @param string $to
     * @return int days from `$from` to `$to`, both included (1 for the same day, 0 or less when `$to` is before `$from`)
     */
    public static function daysInclusive(string $from, string $to): int
    {
        $difference = self::parseDay($from)->diff(self::parseDay($to));
        return ($difference->invert ? -1 : 1) * (int)$difference->days + 1;
    }

    /**
     * @param string $ymd
     * @return Date the framework date of that day, midnight UTC; built new, never from the shared parse cache
     */
    public static function toDate(string $ymd): Date
    {
        self::parseDay($ymd);
        return new Date($ymd . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * @param Date $date
     * @return string the day of a framework date (a `Date` is midnight UTC)
     */
    public static function fromDate(Date $date): string
    {
        return (clone $date)->setTimezone(new DateTimeZone('UTC'))->format(self::DAY_FORMAT);
    }

    /**
     * @param string $value
     * @return bool whether the value is an existing day written `Y-m-d`
     */
    public static function isValidDay(string $value): bool
    {
        $parsedDay = DateTimeImmutable::createFromFormat('!' . self::DAY_FORMAT, $value, new DateTimeZone('UTC'));
        return $parsedDay !== false && $parsedDay->format(self::DAY_FORMAT) === $value;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string the earlier day
     */
    public static function earlierDay(string $first, string $second): string
    {
        return strcmp($first, $second) <= 0 ? $first : $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string the later day
     */
    public static function laterDay(string $first, string $second): string
    {
        return strcmp($first, $second) >= 0 ? $first : $second;
    }

    /**
     * @param string $ymd
     * @return DateTimeImmutable midnight UTC of the day
     * @throws BadRequestException when the value is no existing day written `Y-m-d`
     */
    protected static function parseDay(string $ymd): DateTimeImmutable
    {
        if (!self::isValidDay($ymd)) {
            throw new BadRequestException('Not a day written Y-m-d: ' . mb_substr($ymd, 0, 40));
        }
        return DateTimeImmutable::createFromFormat('!' . self::DAY_FORMAT, $ymd, new DateTimeZone('UTC'));
    }
}
