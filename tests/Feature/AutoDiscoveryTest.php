<?php

use dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService;
use dayemsiddiqui\EloquentDefaults\Services\ModelScannerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache and registrations before each test
    app(ModelDiscoveryService::class)->clearCache();
    Cache::flush();

    // Set up test environment
    Config::set('eloquent-defaults.auto_discovery.enabled', true);
    Config::set('eloquent-defaults.cache.force_in_development', false);
});

afterEach(function () {
    // Clean up any created test model files
    cleanupTestModelFiles();
});

it('can discover models with HasEloquentDefaults trait via auto-discovery', function () {
    createAutoDiscoveryTables();

    // Create test model files in a temporary directory that will be scanned
    $testDir = createTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestCompany.php';
    require_once $testDir.'/TestDepartment.php';
    require_once $testDir.'/TestEmployee.php';

    // Configure scanner to look in our test directory
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);

    // Run auto-discovery
    $scanner->discoverAndRegisterModels();

    // Verify models were discovered and registered
    $discoveryService = app(ModelDiscoveryService::class);
    $providers = $discoveryService->getEloquentDefaultsProviders('Tests\\AutoDiscovery\\TestCompany');

    expect($providers)->toContain('Tests\\AutoDiscovery\\TestDepartment');
    expect($providers)->toContain('Tests\\AutoDiscovery\\TestEmployee');
});

it('respects exclude directories configuration', function () {
    createAutoDiscoveryTables();

    $testDir = createTestModelFiles();
    $excludeDir = $testDir.'/excluded';

    // Create a model in excluded directory
    createExcludedTestModel($excludeDir);

    // Configure scanner
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);

    // Set up exclusion (relative path)
    Config::set('eloquent-defaults.auto_discovery.exclude_directories', ['excluded']);

    // Recreate scanner to pick up new config
    $scanner = new ModelScannerService(app(ModelDiscoveryService::class));
    $scanner->setScanDirectories([$testDir]);

    // Run auto-discovery
    $scanner->discoverAndRegisterModels();

    // Verify excluded model was not discovered
    $discoveryService = app(ModelDiscoveryService::class);
    $providers = $discoveryService->getEloquentDefaultsProviders('Tests\\AutoDiscovery\\TestCompany');

    expect($providers)->not()->toContain('Tests\\AutoDiscovery\\ExcludedModel');
});

it('can cache discovered models for performance', function () {
    createAutoDiscoveryTables();

    $testDir = createTestModelFiles();

    // Force caching even in development
    Config::set('eloquent-defaults.cache.force_in_development', true);

    // Create new scanner instance to pick up config changes
    $scanner = new ModelScannerService(app(ModelDiscoveryService::class));
    $scanner->setScanDirectories([$testDir]);

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestCompany.php';
    require_once $testDir.'/TestDepartment.php';
    require_once $testDir.'/TestEmployee.php';

    // First run should cache the results
    $scanner->discoverAndRegisterModels();

    // Verify cache was created
    $cacheKey = config('eloquent-defaults.cache.key', 'eloquent_defaults.discovered_models');
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second run should use cache
    $cachedResults = Cache::get($cacheKey);
    expect($cachedResults)->toBeArray();
    expect($cachedResults)->not()->toBeEmpty();
});

it('can clear cache and rediscover models', function () {
    createAutoDiscoveryTables();

    $testDir = createTestModelFiles();

    Config::set('eloquent-defaults.cache.force_in_development', true);

    // Create new scanner instance to pick up config changes
    $scanner = new ModelScannerService(app(ModelDiscoveryService::class));
    $scanner->setScanDirectories([$testDir]);

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestCompany.php';
    require_once $testDir.'/TestDepartment.php';
    require_once $testDir.'/TestEmployee.php';

    // Run discovery and cache
    $scanner->discoverAndRegisterModels();
    $cacheKey = config('eloquent-defaults.cache.key', 'eloquent_defaults.discovered_models');
    expect(Cache::has($cacheKey))->toBeTrue();

    // Clear cache
    $scanner->clearCache();
    expect(Cache::has($cacheKey))->toBeFalse();

    // Should work again after cache clear
    $scanner->discoverAndRegisterModels();

    $discoveryService = app(ModelDiscoveryService::class);
    $providers = $discoveryService->getEloquentDefaultsProviders('Tests\\AutoDiscovery\\TestCompany');
    expect($providers)->not()->toBeEmpty();
});

it('handles auto-discovery being disabled', function () {
    createAutoDiscoveryTables();

    $testDir = createTestModelFiles();

    // Clear any previous registrations
    $discoveryService = app(ModelDiscoveryService::class);
    $discoveryService->clearCache();

    // Disable auto-discovery
    Config::set('eloquent-defaults.auto_discovery.enabled', false);

    // Recreate scanner to pick up new config
    $scanner = new ModelScannerService($discoveryService);
    $scanner->setScanDirectories([$testDir]);

    expect($scanner->isAutoDiscoveryEnabled())->toBeFalse();

    // This should not discover anything because it's disabled
    $scanner->discoverAndRegisterModels();

    $providers = $discoveryService->getEloquentDefaultsProviders('Tests\\AutoDiscovery\\TestCompany');

    expect($providers)->toBeEmpty();
});

it('gracefully handles files with syntax errors or missing dependencies', function () {
    createAutoDiscoveryTables();

    $testDir = sys_get_temp_dir().'/eloquent-defaults-test-bad-syntax';

    if (! File::isDirectory($testDir)) {
        File::makeDirectory($testDir, 0755, true);
    }

    // Create a PHP file with syntax errors
    File::put($testDir.'/BadSyntax.php', '<?php class BadSyntax { syntax error here }');

    // Create a PHP file with missing dependency
    File::put($testDir.'/MissingDependency.php', '<?php 
namespace Tests\AutoDiscovery;
use NonExistent\SomeClass;
class MissingDependency extends SomeClass {}');

    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);

    // This should not throw an exception
    expect(fn () => $scanner->discoverAndRegisterModels())->not()->toThrow(Exception::class);

    // Clean up
    File::deleteDirectory($testDir);
});

// Helper functions

function createAutoDiscoveryTables()
{
    Schema::dropIfExists('test_companies');
    Schema::create('test_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::dropIfExists('test_departments');
    Schema::create('test_departments', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('company_id');
        $table->timestamps();
    });

    Schema::dropIfExists('test_employees');
    Schema::create('test_employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('company_id');
        $table->timestamps();
    });
}

function createTestModelFiles(): string
{
    $testDir = sys_get_temp_dir().'/eloquent-defaults-test-models';

    if (! File::isDirectory($testDir)) {
        File::makeDirectory($testDir, 0755, true);
    }

    // Create TestCompany (target model)
    File::put($testDir.'/TestCompany.php', '<?php
namespace Tests\AutoDiscovery;
use Illuminate\Database\Eloquent\Model;

class TestCompany extends Model
{
    protected $table = "test_companies";
    protected $fillable = ["name"];
}');

    // Create TestDepartment (provider model)
    File::put($testDir.'/TestDepartment.php', '<?php
namespace Tests\AutoDiscovery;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestDepartment extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "test_departments";
    protected $fillable = ["name", "company_id"];
    
    protected static function eloquentDefaults(TestCompany $company): array
    {
        return [
            TestDepartment::make(["name" => "IT Department", "company_id" => $company->id]),
            TestDepartment::make(["name" => "HR Department", "company_id" => $company->id]),
        ];
    }
}');

    // Create TestEmployee (provider model)
    File::put($testDir.'/TestEmployee.php', '<?php
namespace Tests\AutoDiscovery;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestEmployee extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "test_employees";
    protected $fillable = ["name", "company_id"];
    
    protected static function eloquentDefaults(TestCompany $company): array
    {
        return [
            TestEmployee::make(["name" => "Default Admin", "company_id" => $company->id]),
        ];
    }
}');

    return $testDir;
}

function createExcludedTestModel(string $excludeDir): void
{
    if (! File::isDirectory($excludeDir)) {
        File::makeDirectory($excludeDir, 0755, true);
    }

    File::put($excludeDir.'/ExcludedModel.php', '<?php
namespace Tests\AutoDiscovery;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class ExcludedModel extends Model
{
    use HasEloquentDefaults;
    
    protected static function eloquentDefaults(TestCompany $company): array
    {
        return [];
    }
}');
}

function cleanupTestModelFiles(): void
{
    $testDirs = [
        sys_get_temp_dir().'/eloquent-defaults-test-models',
        sys_get_temp_dir().'/eloquent-defaults-test-bad-syntax',
    ];

    foreach ($testDirs as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
}
