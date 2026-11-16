<?php

declare(strict_types=1);

namespace DDD\Domain\SiteInsights\Entities\Config;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Exceptions\InternalErrorException;

/**
 * `SiteInsights.opportunities` for one site: the application's file over the defaults, then the site's own
 * `opportunities` settings in `sites.php` over that (setting by setting). Thresholds in clicks or impressions are per
 * 28 days and scaled to the window.
 */
class SiteInsightsOpportunitiesConfig extends ValueObject
{
    /** @var int Fewest days a window needs for any rule */
    public int $minWindowDays = 7;

    /** @var SiteInsightsStrikingDistanceConfig Rule strikingDistanceQuery */
    public SiteInsightsStrikingDistanceConfig $strikingDistanceQuery;

    /** @var SiteInsightsWeakClickRateConfig Rule weakClickRateForPosition */
    public SiteInsightsWeakClickRateConfig $weakClickRateForPosition;

    /** @var SiteInsightsSeveralPagesConfig Rule severalPagesForQuery */
    public SiteInsightsSeveralPagesConfig $severalPagesForQuery;

    /** @var SiteInsightsRisingAndDecliningConfig Rules risingQuery, decliningQuery, risingPage, decliningPage */
    public SiteInsightsRisingAndDecliningConfig $risingAndDeclining;

    public function __construct()
    {
        $this->strikingDistanceQuery = new SiteInsightsStrikingDistanceConfig();
        $this->weakClickRateForPosition = new SiteInsightsWeakClickRateConfig();
        $this->severalPagesForQuery = new SiteInsightsSeveralPagesConfig();
        $this->risingAndDeclining = new SiteInsightsRisingAndDecliningConfig();
        parent::__construct();
    }

    /**
     * @param array $configuredSettings settings in the shape of `opportunities.php` (wrapped here, never passed on)
     * @param string $settingPath where they come from, named in errors
     * @return void applied over the current values
     * @throws InternalErrorException for an unknown rule or setting or a value of the wrong type
     */
    public function applyConfigSettings(array $configuredSettings, string $settingPath): void
    {
        foreach ($configuredSettings as $settingName => $settingValue) {
            $settingName = (string)$settingName;
            if ($settingName === 'minWindowDays') {
                if (!is_int($settingValue) || $settingValue < 1) {
                    throw new InternalErrorException($settingPath . '.minWindowDays must be a positive int');
                }
                $this->minWindowDays = $settingValue;
                continue;
            }
            $ruleConfig = $this->findRuleConfig($settingName);
            if ($ruleConfig === null) {
                throw new InternalErrorException($settingPath . '.' . $settingName . ' is no opportunity rule');
            }
            if (!is_array($settingValue)) {
                throw new InternalErrorException($settingPath . '.' . $settingName . ' must be a list of settings');
            }
            $ruleConfig->applyConfigSettings($settingValue, $settingPath . '.' . $settingName);
        }
    }

    /**
     * @param string $ruleKey
     * @return SiteInsightsOpportunityRuleConfig|null the thresholds of the rule key, null for an unknown key
     */
    public function findRuleConfig(string $ruleKey): ?SiteInsightsOpportunityRuleConfig
    {
        foreach ([$this->strikingDistanceQuery, $this->weakClickRateForPosition, $this->severalPagesForQuery, $this->risingAndDeclining] as $ruleConfig) {
            if ($ruleConfig::RULE_KEY === $ruleKey) {
                return $ruleConfig;
            }
        }
        return null;
    }

    public function uniqueKey(): string
    {
        return static::uniqueKeyStatic((string)spl_object_id($this));
    }
}
