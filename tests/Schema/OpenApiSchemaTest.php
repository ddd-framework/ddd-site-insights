<?php

declare(strict_types=1);

namespace DDD\Tests\SiteInsights\Schema;

use DDD\Presentation\Base\OpenApi\Document;
use DDD\Symfony\Loaders\CustomAnnotationClassLoader;
use DDD\Tests\SiteInsights\Fixture\Controller\FixtureSiteInsightsSitesController;
use DDD\Tests\SiteInsights\Schema\Probe\SiteInsightsSchemaProbeController;
use DDD\Tests\SiteInsights\Support\SiteInsightsTestCase;
use Symfony\Component\Routing\RouteCollection;

/**
 * The schema gate. (1) The committed schema of the module's endpoints (web/schema/site-insights.openapi.json, the
 * source of the npm package's generated types) equals the schema the PHP classes produce; with
 * SITE_INSIGHTS_WRITE_SCHEMA=1 the file is written instead. (2) No schema of the module has an untyped array or an
 * object without properties, and a set's own properties (window, coverage, error) are documented next to its elements.
 */
class OpenApiSchemaTest extends SiteInsightsTestCase
{
    public const string ROUTE_PREFIX = '/api/admin';
    public const string SCHEMA_FILE = 'web/schema/site-insights.openapi.json';
    public const string MODULE_SCHEMA_PREFIX = 'DDD.Domain.SiteInsights.';

    /** @var string[] Fixture controllers whose endpoints form the committed schema */
    public const array FIXTURE_CONTROLLERS = [FixtureSiteInsightsSitesController::class];

    /**
     * @param string[] $controllerClasses
     * @return array the OpenAPI document as JSON-decoded array
     */
    public static function buildDocument(array $controllerClasses): array
    {
        $customAnnotationClassLoader = new CustomAnnotationClassLoader();
        $routeCollection = new RouteCollection();
        foreach ($controllerClasses as $controllerClass) {
            $controllerRouteCollection = $customAnnotationClassLoader->load($controllerClass);
            $controllerRouteCollection->addPrefix(self::ROUTE_PREFIX);
            $routeCollection->addCollection($controllerRouteCollection);
        }
        $document = new Document($routeCollection, self::ROUTE_PREFIX, maxRecursiveSchemaDepth: 50);
        $document->buildDocumentation();
        return json_decode((string)json_encode($document->toObject(cached: false, forPersistence: false), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
    }

    public function testTheCommittedSchemaMatchesThePhpClasses(): void
    {
        $schemaJson = json_encode(self::buildDocument(self::FIXTURE_CONTROLLERS), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $schemaPath = dirname(__DIR__, 2) . '/' . self::SCHEMA_FILE;
        if (getenv('SITE_INSIGHTS_WRITE_SCHEMA') === '1') {
            @mkdir(dirname($schemaPath), 0777, true);
            file_put_contents($schemaPath, $schemaJson);
        }
        self::assertFileExists($schemaPath, 'write it with SITE_INSIGHTS_WRITE_SCHEMA=1 vendor/bin/phpunit tests/Schema');
        self::assertSame(file_get_contents($schemaPath), $schemaJson, 'the PHP classes changed: regenerate with SITE_INSIGHTS_WRITE_SCHEMA=1 and run pnpm generate in web/');
    }

    public function testNoModuleSchemaHasAnUntypedArrayOrAnObjectWithoutProperties(): void
    {
        $document = self::buildDocument([...self::FIXTURE_CONTROLLERS, SiteInsightsSchemaProbeController::class]);
        $moduleSchemas = array_filter(
            $document['components']['schemas'],
            static fn (string $schemaName): bool => str_starts_with($schemaName, self::MODULE_SCHEMA_PREFIX) || str_contains($schemaName, 'SiteInsights'),
            ARRAY_FILTER_USE_KEY
        );
        self::assertNotEmpty($moduleSchemas);
        $problems = [];
        foreach ($moduleSchemas as $schemaName => $schema) {
            $problems = [...$problems, ...$this->findSchemaProblems($schema, $schemaName)];
        }
        self::assertSame([], $problems);
    }

    public function testTheRowsReportDocumentsItsOwnPropertiesNextToItsElements(): void
    {
        $document = self::buildDocument([SiteInsightsSchemaProbeController::class]);
        $rowsSchema = $document['components']['schemas'][self::MODULE_SCHEMA_PREFIX . 'Entities.Search.SearchPerformanceRows'];

        // the decision of B0: SearchPerformanceRows stays the endpoint property (no SearchPerformanceList fallback)
        foreach (['elements', 'provider', 'dimensions', 'window', 'comparisonWindow', 'coverage', 'error'] as $propertyName) {
            self::assertArrayHasKey($propertyName, $rowsSchema['properties'], $propertyName);
        }
        self::assertSame('array', $rowsSchema['properties']['elements']['type']);
        self::assertStringEndsWith('Entities.Search.SearchPerformanceRow', $rowsSchema['properties']['elements']['items']['$ref']);
        self::assertSame(['type' => 'string'], array_intersect_key($rowsSchema['properties']['dimensions']['items'], ['type' => true]));
        $rowSchema = $document['components']['schemas'][self::MODULE_SCHEMA_PREFIX . 'Entities.Search.SearchPerformanceRow'];
        self::assertSame('date', $rowSchema['properties']['date']['format'] ?? null);
    }

    /**
     * @param mixed $schemaPart
     * @param string $path
     * @return string[] untyped arrays and objects without properties below the part
     */
    protected function findSchemaProblems(mixed $schemaPart, string $path): array
    {
        if (!is_array($schemaPart)) {
            return [];
        }
        $problems = [];
        $schemaType = $schemaPart['type'] ?? null;
        if ($schemaType === 'array' && (!isset($schemaPart['items']) || $schemaPart['items'] === [])) {
            $problems[] = $path . ': array without typed items';
        }
        if ($schemaType === 'object' && !isset($schemaPart['properties']) && !isset($schemaPart['$ref']) && !isset($schemaPart['additionalProperties'])) {
            $problems[] = $path . ': object without properties';
        }
        foreach ($schemaPart as $key => $value) {
            $problems = [...$problems, ...$this->findSchemaProblems($value, $path . '.' . $key)];
        }
        return $problems;
    }
}
