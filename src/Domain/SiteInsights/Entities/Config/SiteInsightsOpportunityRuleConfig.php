<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Exceptions\InternalErrorException;

/**
 * The thresholds of one opportunity rule. A setting the configuration leaves out keeps the default of the subclass; an
 * unknown setting or one of the wrong type fails loudly, naming it.
 */
abstract class SiteInsightsOpportunityRuleConfig extends ValueObject
{
    /** @var string The rule's key in `SiteInsights.opportunities` and in a site's `opportunities` */
    public const string RULE_KEY = '';

    /**
     * @param array $configuredSettings the rule's settings as configured (wrapped here, never passed on)
     * @param string $settingPath where the settings come from, named in errors
     * @return void
     * @throws InternalErrorException for an unknown setting or a value of the wrong type
     */
    public function applyConfigSettings(array $configuredSettings, string $settingPath): void
    {
        foreach ($configuredSettings as $settingName => $configuredValue) {
            $settingName = (string)$settingName;
            if (!property_exists($this, $settingName)) {
                throw new InternalErrorException($settingPath . '.' . $settingName . ' is no setting of the rule ' . static::RULE_KEY);
            }
            $this->$settingName = match (true) {
                is_bool($this->$settingName) && is_bool($configuredValue) => $configuredValue,
                is_int($this->$settingName) && is_int($configuredValue) => $configuredValue,
                is_float($this->$settingName) && (is_int($configuredValue) || is_float($configuredValue)) => (float)$configuredValue,
                default => throw new InternalErrorException($settingPath . '.' . $settingName . ' must be ' . get_debug_type($this->$settingName)),
            };
        }
    }

    /**
     * @param int $threshold a threshold in clicks or impressions per 28 days
     * @param int $windowDays
     * @return int the threshold for a window of that many days: ceil(threshold × days / 28)
     */
    public static function scaleToWindow(int $threshold, int $windowDays): int
    {
        return (int)ceil($threshold * $windowDays / 28);
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
