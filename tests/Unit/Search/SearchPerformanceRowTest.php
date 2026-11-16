<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Search;

use DDD\Domain\SiteInsights\Entities\Search\SearchMetrics;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRow;
use DDD\Domain\SiteInsights\Entities\Search\SearchPerformanceRows;
use DDD\Domain\SiteInsights\Utils\SearchDays;
use PHPUnit\Framework\TestCase;

/** A row's identity is the dimension values that are set */
class SearchPerformanceRowTest extends TestCase
{
    public function testIdentity(): void
    {
        $firstRow = $this->createRow('alpha widget', 'desktop');
        $sameDimensionValues = $this->createRow('alpha widget', 'desktop');
        $otherDevice = $this->createRow('alpha widget', 'mobile');
        $withoutDevice = $this->createRow('alpha widget', null);

        $searchPerformanceRows = new SearchPerformanceRows();
        $searchPerformanceRows->add($firstRow, $sameDimensionValues, $otherDevice, $withoutDevice);

        self::assertCount(3, $searchPerformanceRows->getElements(), 'the same dimension values collapse');
        self::assertNotSame($withoutDevice->uniqueKey(), $firstRow->uniqueKey(), 'an unset dimension is not equal to a set one');

        $datedRow = $this->createRow(null, null);
        $datedRow->date = SearchDays::toDate('2026-11-10');
        $otherDayRow = $this->createRow(null, null);
        $otherDayRow->date = SearchDays::toDate('2026-11-11');
        self::assertNotSame($datedRow->uniqueKey(), $otherDayRow->uniqueKey());
    }

    protected function createRow(?string $query, ?string $device): SearchPerformanceRow
    {
        $searchPerformanceRow = new SearchPerformanceRow();
        if ($query !== null) {
            $searchPerformanceRow->query = $query;
        }
        if ($device !== null) {
            $searchPerformanceRow->device = $device;
        }
        $searchPerformanceRow->metrics = SearchMetrics::create(1, 10, 3.0, SearchMetrics::AGGREGATION_BY_PROPERTY);
        return $searchPerformanceRow;
    }
}
