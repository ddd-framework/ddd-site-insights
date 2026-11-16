# mgamadeus/ddd-site-insights -- Site Insights Module

Search Console insights for the `mgamadeus/ddd` framework: live reads through `ddd-argus`, cached answers, nothing
stored, coverage stated in every answer.

**Package:** `mgamadeus/ddd-site-insights` (Composer) and `@mgamadeus/ddd-site-insights` (npm, `web/`), same version
**Namespace:** `DDD\`
**Depends on:** `mgamadeus/ddd` (^3.33.2), `mgamadeus/ddd-argus` (^1.5.1)

> **This module follows all DDD Core conventions** (`vendor/mgamadeus/ddd/AGENTS.md` and its skills) and the Argus
> skill. Module skill: `.claude/skills/ddd-module-site-insights-specialist/SKILL.md`.

## Architecture

```
src/
+-- Modules/SiteInsights/SiteInsightsModule.php
+-- Domain/SiteInsights/
|   +-- Credentials/          [GoogleServiceAccountKey, GoogleAccessTokenGrant: never serialised, never services]
|   +-- Entities/
|   |   +-- Access/           [SiteInsightsAccessCheck(s)]
|   |   +-- Budgets/          [SiteInsightsBudgetCounter(s)]
|   |   +-- Errors/           [SiteInsightsErrorCause]
|   |   +-- Google/           [raw answers in Google's shape: GoogleSearchAnalyticsReport, GoogleSites, GoogleSite, GoogleSitemaps, GoogleUrlInspection, GoogleApiFailure]
|   |   +-- Search/           [neutral reports and coverage: SearchPerformanceRows, SearchWindow, SearchRowsCoverage, SearchQuestion, …]
|   |   +-- Sites/            [InsightsSite(s), InsightsPageGroup(s), InsightsProviderCapabilities]
|   +-- Exceptions/           [SiteInsightsProviderUnavailableException (503), SiteInsightsBudgetExceededException (429)]
|   +-- Repo/Argus/Google/    [Argus repositories of the raw answers]
|   +-- Services/             [public: config, sites, access policy, credentials, tokens, request builder, source, windows, budgets, access checks]
|   +-- Utils/SearchDays.php  [day arithmetic on UTC days]
+-- Presentation/Api/Admin/SiteInsights/ [controller traits and DTOs]
+-- Symfony/Commands/SiteInsights/       [SiteInsightsCommand and the commands]
config/app/SiteInsights/      [sites, credentials, cache, budgets, opportunities]
tests/                        [synthetic fixtures, no network; Schema/ writes web/schema/site-insights.openapi.json]
web/                          [npm package: generated types, window logic]
```

## Rules of this module

- Every Google call goes through `GoogleApiRequestsService::buildPayload()`: `EXTERNAL_CALL` and `PRIVATE_TRANSPORT`
  always, the token only in the `Authorization` header; without a token nothing is built. The token exchange never
  goes through Argus.
- Never print, log or cache environment variables, keys or tokens; `check-access` prints states only.
- Every endpoint and command asks `SiteInsightsAccessPolicyService` before any cache read.
- Days: `SearchDays` only; a `Date` is created with `SearchDays::toDate()` and never modified.
- Nullable properties stay uninitialised (no `= null`) and are read with `?? null`.
- Services are resolved from the container by class id at use, never kept in properties; extension points are
  replaced by registering a subclass under the module's class id.
- Fixtures are synthetic (`example.test`, invented queries and numbers); the test key is generated at test time.
  Tests never use the network.
- The repository is public: no names of applications or companies using it, no local paths, no internal hostnames.

## API change workflow

1. Change the PHP classes; `vendor/bin/phpunit`.
2. `SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema` writes `web/schema/site-insights.openapi.json`.
3. In `web/`: `pnpm generate`, `pnpm typecheck`, `pnpm test`, `pnpm build`.
4. CI fails when schema and PHP classes or generated files and schema drift.

## Release

`composer.json` and `web/package.json` carry the same version; a tag `vX.Y.Z` publishes the npm package
(`release.yml`, trusted publishing; a pre-release goes to the `next` dist-tag) and Packagist reads the tag.
