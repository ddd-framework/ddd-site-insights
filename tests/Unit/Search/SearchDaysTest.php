<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Utils\SearchDays;
use DDD\Infrastructure\Base\DateTime\Date;
use PHPUnit\Framework\TestCase;

/**
 * Day arithmetic runs on UTC days whatever PHP's default zone is; a framework Date is built new and never modified.
 */
class SearchDaysTest extends TestCase
{
    protected string $defaultTimeZoneBefore;

    protected function setUp(): void
    {
        $this->defaultTimeZoneBefore = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimeZoneBefore);
    }

    /** @return string[][] */
    public static function provideTimeZones(): array
    {
        return [['Pacific/Auckland'], ['America/Los_Angeles'], ['UTC']];
    }

    /** @dataProvider provideTimeZones */
    public function testArithmeticIsTheSameInEveryDefaultTimeZone(string $defaultTimeZone): void
    {
        date_default_timezone_set($defaultTimeZone);

        self::assertSame('2026-11-13', SearchDays::addDays('2026-11-16', -3));
        self::assertSame('2026-11-07', SearchDays::addDays('2026-11-13', -6));
        self::assertSame('2025-07-16', SearchDays::subtractMonthsClamped('2026-11-16', 16));
        self::assertSame('2025-07-19', SearchDays::addDays(SearchDays::subtractMonthsClamped('2026-11-16', 16), 3));
        self::assertSame('2026-02-28', SearchDays::subtractMonthsClamped('2026-08-31', 6));
        self::assertSame('2028-02-29', SearchDays::subtractMonthsClamped('2028-08-31', 6));
        self::assertSame(7, SearchDays::daysInclusive('2028-02-24', '2028-03-01'));
        self::assertSame(1, SearchDays::daysInclusive('2026-11-13', '2026-11-13'));
        self::assertSame(365, SearchDays::daysInclusive('2025-11-14', '2026-11-13'));
        self::assertSame('2026-11-16', SearchDays::todayInZone('America/Los_Angeles', 1794859200));
        self::assertSame('2026-11-17', SearchDays::todayInZone('Pacific/Auckland', 1794859200));
    }

    /** @dataProvider provideTimeZones */
    public function testToDateYieldsTheSameDayInEveryDefaultTimeZone(string $defaultTimeZone): void
    {
        date_default_timezone_set($defaultTimeZone);

        $date = SearchDays::toDate('2026-11-10');

        self::assertSame('2026-11-10', $date->format('Y-m-d'));
        self::assertSame('2026-11-10', SearchDays::fromDate($date));
        self::assertSame('UTC', $date->getTimezone()->getName());
    }

    public function testValidDays(): void
    {
        self::assertTrue(SearchDays::isValidDay('2028-02-29'));
        self::assertFalse(SearchDays::isValidDay('2026-02-29'));
        self::assertFalse(SearchDays::isValidDay('2026-2-1'));
        self::assertFalse(SearchDays::isValidDay('2026-11-10 00:00:00'));
    }

    public function testAHydratedDateIsNeverModified(): void
    {
        $hydratedDate = Date::fromString('2026-11-10');
        $hydratedTimestampBefore = $hydratedDate->getTimestamp();

        SearchDays::addDays(SearchDays::fromDate($hydratedDate), -30);
        SearchDays::subtractMonthsClamped(SearchDays::fromDate($hydratedDate), 6);
        $builtDate = SearchDays::toDate(SearchDays::fromDate($hydratedDate));

        self::assertSame($hydratedTimestampBefore, $hydratedDate->getTimestamp());
        self::assertNotSame($hydratedDate, $builtDate);
        self::assertSame($hydratedTimestampBefore, Date::fromString('2026-11-10')->getTimestamp());
    }
}
