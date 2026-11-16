<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Unit\Commands;

use DDD\Domain\Common\Entities\Accounts\Account;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsCheckAccess;
use DDD\Symfony\Commands\SiteInsights\SiteInsightsSites;
use DDD\Tests\SiteInsights\Support\CapturingNetwork;
use DDD\Tests\SiteInsights\Support\FixedTokenCredentialsProviderService;
use DDD\Tests\SiteInsights\Support\GoogleFixtures;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use DDD\Tests\SiteInsights\Support\SyntheticServiceAccountKey;
use DDD\Tests\SiteInsights\Support\TestAccounts;
use DDD\Tests\SiteInsights\Support\TestAccountSiteInsightsCheckAccess;
use DDD\Tests\SiteInsights\Support\TestAccountSiteInsightsSites;
use DDD\Tests\SiteInsights\Support\TestConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** The console commands of B1: payloads, formats, exit codes, and nothing secret on any stream */
class SiteInsightsCommandsTest extends SiteInsightsTestCase
{
    protected ?Account $cliAccount = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliAccount = TestAccounts::admin();
    }

    public function testSitesAsJson(): void
    {
        $commandTester = new CommandTester($this->createSitesCommand());

        $exitCode = $commandTester->execute(['--format' => 'json']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $payload = json_decode($commandTester->getDisplay(), true);
        self::assertSame(['insightsSites'], array_keys($payload));
        self::assertSame(TestConfig::SITE_KEY, $payload['insightsSites']['elements'][0]['key']);
        self::assertSame('America/Los_Angeles', $payload['insightsSites']['elements'][0]['capabilities']['dayTimeZone']);
        self::assertStringNotContainsString("\n  ", $commandTester->getDisplay(), 'compact unless --pretty');
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    public function testSitesAsTableAndToon(): void
    {
        $tableTester = new CommandTester($this->createSitesCommand());
        self::assertSame(Command::SUCCESS, $tableTester->execute([]));
        self::assertStringContainsString(TestConfig::SITE_KEY, $tableTester->getDisplay());
        self::assertStringContainsString('searchReader (revision 1)', $tableTester->getDisplay());

        $toonTester = new CommandTester($this->createSitesCommand());
        self::assertSame(Command::SUCCESS, $toonTester->execute(['--format' => 'toon']));
        self::assertStringContainsString('elementsInToonFormat', $toonTester->getDisplay());
    }

    public function testAnAccountThatMayNotViewGetsNothing(): void
    {
        $this->cliAccount = TestAccounts::nonAdmin();
        $commandTester = new CommandTester($this->createSitesCommand());

        self::assertSame(Command::FAILURE, $commandTester->execute(['--format' => 'json']));
        self::assertStringNotContainsString(TestConfig::SITE_KEY, $commandTester->getDisplay());
    }

    public function testInvalidInput(): void
    {
        $unknownFormatTester = new CommandTester($this->createSitesCommand());
        self::assertSame(Command::INVALID, $unknownFormatTester->execute(['--format' => 'xml']));

        $unknownSiteTester = new CommandTester($this->createCheckAccessCommand());
        self::assertSame(Command::INVALID, $unknownSiteTester->execute(['site' => 'other-site']));
    }

    public function testCheckAccessPrintsStatesOnly(): void
    {
        CapturingNetwork::install(
            GoogleFixtures::response('sites-list'),
            GoogleFixtures::response('site-get'),
            GoogleFixtures::response('searchanalytics-totals'),
            GoogleFixtures::response('sitemaps-list'),
            GoogleFixtures::response('inspection-indexed')
        );
        $commandTester = new CommandTester($this->createCheckAccessCommand());

        $exitCode = $commandTester->execute(['site' => TestConfig::SITE_KEY, '--format' => 'json', '--pretty' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $payload = json_decode($commandTester->getDisplay(), true);
        self::assertCount(8, $payload['siteInsightsAccessChecks']['elements']);
        self::assertSame(['ok'], array_values(array_unique(array_column($payload['siteInsightsAccessChecks']['elements'], 'state'))));
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    public function testCheckAccessExitsWithOneWhenACapabilityFailed(): void
    {
        CapturingNetwork::install(
            GoogleFixtures::response('sites-list'),
            GoogleFixtures::response('site-get'),
            GoogleFixtures::response('error-403-service-disabled', 403),
            GoogleFixtures::response('sitemaps-list'),
            GoogleFixtures::response('inspection-indexed')
        );
        $commandTester = new CommandTester($this->createCheckAccessCommand());

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertMatchesRegularExpression('/searchAnalytics\s*\|\s*failed\s*\|\s*apiNotEnabled/', $commandTester->getDisplay());
        $this->assertNoSecretIn($commandTester->getDisplay());
    }

    protected function assertNoSecretIn(string $output): void
    {
        self::assertStringNotContainsString(FixedTokenCredentialsProviderService::TOKEN_MARKER, $output);
        self::assertStringNotContainsString(SyntheticServiceAccountKey::CLIENT_EMAIL, $output);
        self::assertStringNotContainsString(TestConfig::KEY_ENVIRONMENT_VARIABLE, $output);
        self::assertStringNotContainsString(substr(SyntheticServiceAccountKey::get()->encodedKeyFile, 0, 40), $output);
        foreach (SyntheticServiceAccountKey::get()->getPrivateKeyMarkerLines() as $privateKeyLine) {
            self::assertStringNotContainsString($privateKeyLine, $output);
        }
    }

    protected function createSitesCommand(): SiteInsightsSites
    {
        return new TestAccountSiteInsightsSites($this->cliAccount);
    }

    protected function createCheckAccessCommand(): SiteInsightsCheckAccess
    {
        return new TestAccountSiteInsightsCheckAccess($this->cliAccount);
    }
}
