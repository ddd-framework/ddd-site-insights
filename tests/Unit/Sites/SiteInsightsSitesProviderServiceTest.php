<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Sites;

use DDD\Domain\SiteInsights\Services\InsightsSitesService;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Exceptions\NotFoundException;
use DDD\Infrastructure\Services\DDDService;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\TestConfig;

/** Sites come from configuration and every entry is validated */
class SiteInsightsSitesProviderServiceTest extends SiteInsightsTestCase
{
    public function testConfiguredSites(): void
    {
        $insightsSites = $this->getInsightsSitesService()->getSites();

        self::assertCount(1, $insightsSites->getElements());
        $insightsSite = $insightsSites->first();
        self::assertSame(TestConfig::SITE_KEY, $insightsSite->key);
        self::assertSame(TestConfig::PROPERTY, $insightsSite->property);
        self::assertSame(1, $insightsSite->credentialsRevision);
        self::assertSame(['example'], $insightsSite->brandQueryPatterns);
        self::assertCount(2, $insightsSite->pageGroups->getElements());
        self::assertSame('America/Los_Angeles', $insightsSite->capabilities->dayTimeZone);
        self::assertSame(16, $insightsSite->capabilities->retentionMonths);
        self::assertSame('https://example.test/', $insightsSite->getPropertyRootUrl());
    }

    public function testAnUnknownSiteIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getInsightsSitesService()->getSite('other-site');
    }

    public function testPageGroupOfUrl(): void
    {
        $insightsSite = $this->getInsightsSitesService()->getSite(TestConfig::SITE_KEY);

        self::assertSame('guides', $this->getInsightsSitesService()->findPageGroupOfUrl($insightsSite, 'https://example.test/guides/alpha/')?->key);
        self::assertSame('shop', $this->getInsightsSitesService()->findPageGroupOfUrl($insightsSite, 'https://example.test/cart')?->key);
        self::assertNull($this->getInsightsSitesService()->findPageGroupOfUrl($insightsSite, 'https://example.test/cart/items'));
        self::assertNull($this->getInsightsSitesService()->findPageGroupOfUrl($insightsSite, 'https://example.test/'));
    }

    public function testADomainProperty(): void
    {
        $siteEntry = TestConfig::getDefaultSites()[0];
        $siteEntry['property'] = 'sc-domain:example.test';
        TestConfig::apply(['sites' => ['sites' => [$siteEntry]]]);

        $insightsSite = $this->getInsightsSitesService()->getSite(TestConfig::SITE_KEY);

        self::assertTrue($insightsSite->isDomainProperty());
        self::assertSame('https://example.test/', $insightsSite->getPropertyRootUrl());
    }

    /** @return array[] setting changed, value, part of the expected message */
    public static function provideInvalidEntries(): array
    {
        return [
            'key with upper case' => ['key', 'Example', 'the key must match'],
            'unknown provider' => ['provider', 'other-provider', 'provider'],
            'property without slash' => ['property', 'https://example.test', 'property'],
            'property of another kind' => ['property', 'example.test', 'property'],
            'unknown credentials entry' => ['credentials', 'unknownReader', 'not configured'],
            'lookaround' => ['brandQueryPatterns', ['(?!example)'], 'RE2'],
            'backreference' => ['brandQueryPatterns', ['(a)\\1'], 'RE2'],
            'not compiling' => ['brandQueryPatterns', ['(unclosed'], 'does not compile'],
            'page group without patterns' => ['pageGroups', [['key' => 'empty', 'label' => 'Empty', 'pathPatterns' => []]], 'no path pattern'],
            'page group twice' => ['pageGroups', [['key' => 'a', 'pathPatterns' => ['^/a']], ['key' => 'a', 'pathPatterns' => ['^/b']]], 'twice'],
        ];
    }

    /** @dataProvider provideInvalidEntries */
    public function testInvalidEntriesAreRefused(string $settingName, mixed $value, string $expectedMessagePart): void
    {
        $siteEntry = TestConfig::getDefaultSites()[0];
        $siteEntry[$settingName] = $value;
        TestConfig::apply(['sites' => ['sites' => [$siteEntry]]]);

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($expectedMessagePart, '/') . '/');
        $this->getInsightsSitesService()->getSites();
    }

    public function testADuplicateSiteKeyIsRefused(): void
    {
        $siteEntry = TestConfig::getDefaultSites()[0];
        TestConfig::apply(['sites' => ['sites' => [$siteEntry, $siteEntry]]]);

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessageMatches('/configured twice/');
        $this->getInsightsSitesService()->getSites();
    }

    protected function getInsightsSitesService(): InsightsSitesService
    {
        return DDDService::instance()->getService(InsightsSitesService::class);
    }
}
