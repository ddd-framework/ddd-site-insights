<?php

declare(strict_types=1);

/**
 * Framework objects assume a booted kernel (entities and services reach for the container). The suite installs a
 * minimal container and registers this module's config directory, so Config::get('SiteInsights.…') answers with the
 * module defaults. No application kernel, no database, no network.
 */

use DDD\Infrastructure\Libs\Config;
use DDD\Tests\SiteInsights\Support\TestContainer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

$moduleRoot = dirname(__DIR__);
require $moduleRoot . '/vendor/autoload.php';

if (!defined('APP_ROOT_DIR')) {
    define('APP_ROOT_DIR', $moduleRoot);
}

$testCacheDir = sys_get_temp_dir() . '/ddd_site_insights_phpunit_cache';
@mkdir($testCacheDir, 0777, true);
@touch($testCacheDir . '/service_class_map.php');
(new ReflectionProperty(DDD\DDDBundle::class, 'defaultContainer'))->setValue(null, new TestContainer(new ParameterBag([
    'kernel.cache_dir' => $testCacheDir,
    'kernel.project_dir' => $moduleRoot,
    'kernel.environment' => 'test',
])));

// entities with lazy relations (an Account of a test) ask for the application's default repository type
$_ENV['LAZYLOAD_DEFAULT_REPO_TYPE'] ??= DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo::DB;

Config::addConfigDirectory($moduleRoot . '/config/app', isModule: true);
