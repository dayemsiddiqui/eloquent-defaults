<?php

use dayemsiddiqui\EloquentDefaults\Services\ModelScannerService;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    app(\dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService::class)->clearCache();
});

afterEach(function () {
    // Clean up any created test model files
    cleanupEloquentDefaultsTestModelFiles();
});

// Test models
class User extends Model
{
    protected $fillable = ['name', 'email'];

    protected $table = 'users';
}

class Plan extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['name', 'price', 'user_id'];

    protected $table = 'plans';

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Plan::make(['name' => 'Free Plan', 'price' => 0, 'user_id' => $user->id]),
            Plan::make(['name' => 'Pro Plan', 'price' => 10, 'user_id' => $user->id]),
        ];
    }
}

class Setting extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['key', 'value', 'user_id'];

    protected $table = 'settings';

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Setting::make(['key' => 'theme', 'value' => 'light', 'user_id' => $user->id]),
            Setting::make(['key' => 'notifications', 'value' => 'enabled', 'user_id' => $user->id]),
        ];
    }
}

it('can register models with HasEloquentDefaults trait', function () {
    // Create temporary model files and use auto-discovery
    $testDir = createEloquentDefaultsTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPlan.php';
    require_once $testDir.'/TestSetting.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    $discoveryService = app(\dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService::class);

    // Models using the trait should auto-register
    $providers = $discoveryService->getEloquentDefaultsProviders(User::class);

    expect($providers)->toContain('Tests\\EloquentDefaults\\TestPlan');
    expect($providers)->toContain('Tests\\EloquentDefaults\\TestSetting');
});

it('can detect target model from method signature', function () {
    $targetModel = Plan::getEloquentDefaultsTargetModel();

    expect($targetModel)->toBe(User::class);
});

it('can create default models when target is created', function () {
    // Set up database tables
    createUsersTable();
    createPlansTable();
    createSettingsTable();

    // Create temporary model files and use auto-discovery
    $testDir = createEloquentDefaultsTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPlan.php';
    require_once $testDir.'/TestSetting.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    // Create a user - this should trigger default creation
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    // Check that plans were created (using database table directly since test models are in different namespace)
    $plans = \Illuminate\Support\Facades\DB::table('plans')->where('user_id', $user->id)->get();
    expect($plans)->toHaveCount(2);
    expect($plans->pluck('name')->toArray())->toBe(['Free Plan', 'Pro Plan']);
    expect($plans->pluck('price')->toArray())->toBe([0, 10]);

    // Check that settings were created
    $settings = \Illuminate\Support\Facades\DB::table('settings')->where('user_id', $user->id)->get();
    expect($settings)->toHaveCount(2);
    expect($settings->pluck('key')->toArray())->toBe(['theme', 'notifications']);
    expect($settings->pluck('value')->toArray())->toBe(['light', 'enabled']);
});

it('validates that eloquentDefaults method exists', function () {
    expect(function () {
        new class extends Model
        {
            use HasEloquentDefaults;

            protected $table = 'invalid_model';
        };
    })->toThrow(\dayemsiddiqui\EloquentDefaults\Exceptions\InvalidConfigurationException::class);
});

it('validates that eloquentDefaults method has typed parameter', function () {
    expect(function () {
        new class extends Model
        {
            use HasEloquentDefaults;

            protected $table = 'invalid_model';

            protected function eloquentDefaults($user): array
            {
                return [];
            }
        };
    })->toThrow(\dayemsiddiqui\EloquentDefaults\Exceptions\InvalidConfigurationException::class);
});

it('validates that eloquentDefaults returns array of models', function () {
    createUsersTable();
    createInvalidTable();

    $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);

    expect(function () use ($user) {
        $modelClass = new class extends Model
        {
            use HasEloquentDefaults;

            protected $table = 'invalid_model';

            protected static function eloquentDefaults(User $user): array
            {
                return ['not a model'];
            }
        };

        return $modelClass::createEloquentDefaults($user);
    })->toThrow(\dayemsiddiqui\EloquentDefaults\Exceptions\InvalidConfigurationException::class);
});

it('can debug registrations', function () {
    // Create temporary model files and use auto-discovery
    $testDir = createEloquentDefaultsTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPlan.php';
    require_once $testDir.'/TestSetting.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    $eloquentDefaults = app(\dayemsiddiqui\EloquentDefaults\EloquentDefaults::class);

    $debug = $eloquentDefaults->debugRegistrations();

    expect($debug)->toHaveKey(User::class);
    expect($debug[User::class]['provider_models'])->toContain('Tests\\EloquentDefaults\\TestPlan');
    expect($debug[User::class]['provider_models'])->toContain('Tests\\EloquentDefaults\\TestSetting');
    expect($debug[User::class]['type'])->toBe('HasEloquentDefaults');
});

// Helper functions to create test tables
function createUsersTable()
{
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
}

function createPlansTable()
{
    Schema::dropIfExists('plans');
    Schema::create('plans', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('price', 8, 2);
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
    });
}

function createSettingsTable()
{
    Schema::dropIfExists('settings');
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key');
        $table->string('value');
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
    });
}

function createInvalidTable()
{
    if (! Schema::hasTable('invalid_model')) {
        Schema::create('invalid_model', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
}

// Helper functions for auto-discovery tests

function createEloquentDefaultsTestModelFiles(): string
{
    $testDir = sys_get_temp_dir().'/eloquent-defaults-main-test-models';

    if (! File::isDirectory($testDir)) {
        File::makeDirectory($testDir, 0755, true);
    }

    // Create TestPlan (provider model)
    File::put($testDir.'/TestPlan.php', '<?php
namespace Tests\EloquentDefaults;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestPlan extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "plans";
    protected $fillable = ["name", "price", "user_id"];
    
    protected static function eloquentDefaults(\\User $user): array
    {
        return [
            static::make(["name" => "Free Plan", "price" => 0, "user_id" => $user->id]),
            static::make(["name" => "Pro Plan", "price" => 10, "user_id" => $user->id]),
        ];
    }
}');

    // Create TestSetting (provider model)
    File::put($testDir.'/TestSetting.php', '<?php
namespace Tests\EloquentDefaults;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestSetting extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "settings";
    protected $fillable = ["key", "value", "user_id"];
    
    protected static function eloquentDefaults(\\User $user): array
    {
        return [
            static::make(["key" => "theme", "value" => "light", "user_id" => $user->id]),
            static::make(["key" => "notifications", "value" => "enabled", "user_id" => $user->id]),
        ];
    }
}');

    return $testDir;
}

function cleanupEloquentDefaultsTestModelFiles(): void
{
    $testDir = sys_get_temp_dir().'/eloquent-defaults-main-test-models';

    if (File::isDirectory($testDir)) {
        File::deleteDirectory($testDir);
    }
}
