<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Search;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\Date;

/** The window a caller asks for: a preset or explicit days, a comparison, search type and data state */
class SearchWindowRequest extends ValueObject
{
    public const string PRESET_7_DAYS = '7d';
    public const string PRESET_28_DAYS = '28d';
    public const string PRESET_90_DAYS = '90d';
    public const string PRESET_6_MONTHS = '6m';
    public const string PRESET_12_MONTHS = '12m';
    public const string PRESET_16_MONTHS = '16m';

    /** @var string[] Presets of a number of days */
    public const array DAY_PRESETS = [self::PRESET_7_DAYS => 7, self::PRESET_28_DAYS => 28, self::PRESET_90_DAYS => 90];

    /** @var string[] Presets of a number of months */
    public const array MONTH_PRESETS = [self::PRESET_6_MONTHS => 6, self::PRESET_12_MONTHS => 12, self::PRESET_16_MONTHS => 16];

    public const string COMPARE_NONE = 'none';
    public const string DATA_STATE_FINAL = 'final';
    public const string DATA_STATE_ALL = 'all';
    public const string SEARCH_TYPE_WEB = 'web';

    /** @var string 7d, 28d, 90d, 6m, 12m or 16m; ignored when `from` and `to` are given */
    public string $preset = self::PRESET_28_DAYS;

    /** @var Date|null First day (provider days); with `to` instead of the preset */
    public ?Date $from;

    /** @var Date|null Last day (provider days) */
    public ?Date $to;

    /** @var string none, previousPeriod or previousYear */
    public string $compare = self::COMPARE_NONE;

    /** @var string web, image, video, news, discover or googleNews */
    public string $searchType = self::SEARCH_TYPE_WEB;

    /** @var string final or all */
    public string $dataState = self::DATA_STATE_FINAL;

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
