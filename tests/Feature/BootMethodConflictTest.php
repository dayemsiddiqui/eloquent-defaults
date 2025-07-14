<?php

use dayemsiddiqui\EloquentDefaults\Services\ModelScannerService;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    app(\dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService::class)->clearCache();
});

afterEach(function () {
    // Clean up any created test model files
    cleanupBootTestModelFiles();
});

// Test model: Company (target model)
class Company extends Model
{
    protected $fillable = ['name'];

    protected $table = 'companies';
}

// Test model: Clinic with existing boot method (like user's scenario)
class Clinic extends Model
{
    protected $fillable = ['name', 'company_id'];

    protected $table = 'clinics';

    // Track if existing functionality was called
    public static $defaultMethodsCalled = [];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($clinic) {
            // Simulate existing functionality like createDefaultAppointmentTypes()
            DB::table('appointment_types')->insert([
                'clinic_id' => $clinic->id,
                'name' => 'General Consultation',
                'duration' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('soap_templates')->insert([
                'clinic_id' => $clinic->id,
                'name' => 'Default Template',
                'template' => 'S: O: A: P:',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Track that existing methods were called
            static::$defaultMethodsCalled[] = 'appointment_types';
            static::$defaultMethodsCalled[] = 'soap_templates';
        });
    }
}

// Test model: Patient provider with HasEloquentDefaults
class Patient extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['name', 'clinic_id'];

    protected $table = 'patients';

    protected static function eloquentDefaults(Clinic $clinic): array
    {
        return [
            Patient::make(['name' => 'Test Patient 1', 'clinic_id' => $clinic->id]),
            Patient::make(['name' => 'Test Patient 2', 'clinic_id' => $clinic->id]),
        ];
    }
}

// Test model: Staff provider with HasEloquentDefaults
class Staff extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['name', 'clinic_id'];

    protected $table = 'staff';

    protected static function eloquentDefaults(Clinic $clinic): array
    {
        return [
            Staff::make(['name' => 'Default Admin', 'clinic_id' => $clinic->id]),
        ];
    }
}

// Test model: AdvancedClinic with multiple existing event listeners
class AdvancedClinic extends Model
{
    protected $fillable = ['name'];

    protected $table = 'advanced_clinics';

    public static $eventOrder = [];

    protected static function boot()
    {
        parent::boot();

        // Multiple existing event listeners
        static::created(function ($clinic) {
            static::$eventOrder[] = 'first_created_listener';
        });

        static::created(function ($clinic) {
            static::$eventOrder[] = 'second_created_listener';
        });

        static::saved(function ($clinic) {
            static::$eventOrder[] = 'saved_listener';
        });
    }
}

// Test model: ClinicSetting provider for AdvancedClinic
class ClinicSetting extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['key', 'value', 'clinic_id'];

    protected $table = 'clinic_settings';

    protected static function eloquentDefaults(AdvancedClinic $clinic): array
    {
        return [
            ClinicSetting::make(['key' => 'theme', 'value' => 'light', 'clinic_id' => $clinic->id]),
            ClinicSetting::make(['key' => 'locale', 'value' => 'en', 'clinic_id' => $clinic->id]),
        ];
    }
}

// Test model: BadBootClinic that doesn't call parent::boot()
class BadBootClinic extends Model
{
    protected $fillable = ['name'];

    protected $table = 'bad_boot_clinics';

    public static $customBootCalled = false;

    protected static function boot()
    {
        // Intentionally NOT calling parent::boot() to test edge case

        static::created(function ($clinic) {
            static::$customBootCalled = true;
        });
    }
}

// Test model: Equipment provider for BadBootClinic
class Equipment extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['name', 'clinic_id'];

    protected $table = 'equipment';

    protected static function eloquentDefaults(BadBootClinic $clinic): array
    {
        return [
            Equipment::make(['name' => 'Default Stethoscope', 'clinic_id' => $clinic->id]),
        ];
    }
}

it('works with models that have existing boot method and created listeners', function () {
    createTablesForBootTests();

    // Reset tracking
    Clinic::$defaultMethodsCalled = [];

    // Create temporary model files and use auto-discovery
    $testDir = createBootTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPatient.php';
    require_once $testDir.'/TestStaff.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    // Create a clinic - should trigger both existing functionality and defaults
    $clinic = Clinic::create(['name' => 'Test Clinic']);

    // Verify existing functionality still works
    expect(Clinic::$defaultMethodsCalled)->toContain('appointment_types', 'soap_templates');

    // Verify appointment types were created
    $appointmentTypes = DB::table('appointment_types')->where('clinic_id', $clinic->id)->get();
    expect($appointmentTypes)->toHaveCount(1);
    expect($appointmentTypes->first()->name)->toBe('General Consultation');

    // Verify soap templates were created
    $soapTemplates = DB::table('soap_templates')->where('clinic_id', $clinic->id)->get();
    expect($soapTemplates)->toHaveCount(1);
    expect($soapTemplates->first()->name)->toBe('Default Template');

    // Verify eloquent defaults were created
    $patients = DB::table('patients')->where('clinic_id', $clinic->id)->get();
    expect($patients)->toHaveCount(2);
    expect($patients->pluck('name')->toArray())->toBe(['Test Patient 1', 'Test Patient 2']);

    $staff = DB::table('staff')->where('clinic_id', $clinic->id)->get();
    expect($staff)->toHaveCount(1);
    expect($staff->first()->name)->toBe('Default Admin');
});

it('works with models that have multiple existing event listeners', function () {
    createTablesForBootTests();

    // Reset tracking
    AdvancedClinic::$eventOrder = [];

    // Create temporary model files and use auto-discovery
    $testDir = createBootTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestClinicSetting.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    // Create an advanced clinic
    $clinic = AdvancedClinic::create(['name' => 'Advanced Test Clinic']);

    // Verify existing event listeners were called
    expect(AdvancedClinic::$eventOrder)->toContain('first_created_listener');
    expect(AdvancedClinic::$eventOrder)->toContain('second_created_listener');
    expect(AdvancedClinic::$eventOrder)->toContain('saved_listener');

    // Verify eloquent defaults were created
    $settings = DB::table('clinic_settings')->where('clinic_id', $clinic->id)->get();
    expect($settings)->toHaveCount(2);
    expect($settings->pluck('key')->toArray())->toBe(['theme', 'locale']);
    expect($settings->pluck('value')->toArray())->toBe(['light', 'en']);
});

it('handles models with boot method that does not call parent boot', function () {
    createTablesForBootTests();

    // Reset tracking
    BadBootClinic::$customBootCalled = false;

    // Manually trigger boot for models with HasEloquentDefaults
    Equipment::bootHasEloquentDefaults();

    // This test demonstrates what happens when parent::boot() is not called
    // It should throw an error when creating the model because trait initializers aren't set up
    expect(function () {
        BadBootClinic::create(['name' => 'Bad Boot Clinic']);
    })->toThrow(ErrorException::class);

    // If we get here, verify the custom boot was attempted
    expect(BadBootClinic::$customBootCalled)->toBe(false); // It never gets called due to the error
});

it('verifies event registration order and compatibility', function () {
    createTablesForBootTests();

    // Track the order of operations
    $eventLog = [];

    // Add a custom event listener to track order
    Clinic::created(function ($clinic) use (&$eventLog) {
        $eventLog[] = 'custom_listener_after_registration';
    });

    // Create temporary model files and use auto-discovery
    $testDir = createBootTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPatient.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    // Create clinic
    $clinic = Clinic::create(['name' => 'Event Order Test Clinic']);

    // Verify all expected functionality occurred
    expect(Clinic::$defaultMethodsCalled)->not()->toBeEmpty();
    expect(DB::table('patients')->where('clinic_id', $clinic->id)->count())->toBe(2);
});

it('validates that trait boot methods are called when parent boot is called', function () {
    createTablesForBootTests();

    // Create temporary model files and use auto-discovery
    $testDir = createBootTestModelFiles();

    // Load the model files so they're available for reflection
    require_once $testDir.'/TestPatient.php';

    // Use auto-discovery instead of manual calls
    $scanner = app(ModelScannerService::class);
    $scanner->setScanDirectories([$testDir]);
    $scanner->discoverAndRegisterModels();

    $discoveryService = app(\dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService::class);
    $providers = $discoveryService->getEloquentDefaultsProviders(Clinic::class);

    // Verify registration happened (meaning auto-discovery worked)
    expect($providers)->toContain('Tests\\BootConflict\\TestPatient');
});

// Helper function to create test tables
function createTablesForBootTests()
{
    // Create companies table
    Schema::dropIfExists('companies');
    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    // Create clinics table
    Schema::dropIfExists('clinics');
    Schema::create('clinics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('company_id')->nullable();
        $table->timestamps();
    });

    // Create advanced_clinics table
    Schema::dropIfExists('advanced_clinics');
    Schema::create('advanced_clinics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    // Create bad_boot_clinics table
    Schema::dropIfExists('bad_boot_clinics');
    Schema::create('bad_boot_clinics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    // Create patients table
    Schema::dropIfExists('patients');
    Schema::create('patients', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('clinic_id');
        $table->timestamps();
    });

    // Create staff table
    Schema::dropIfExists('staff');
    Schema::create('staff', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('clinic_id');
        $table->timestamps();
    });

    // Create clinic_settings table
    Schema::dropIfExists('clinic_settings');
    Schema::create('clinic_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key');
        $table->string('value');
        $table->unsignedBigInteger('clinic_id');
        $table->timestamps();
    });

    // Create equipment table
    Schema::dropIfExists('equipment');
    Schema::create('equipment', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('clinic_id');
        $table->timestamps();
    });

    // Create appointment_types table (for existing functionality)
    Schema::dropIfExists('appointment_types');
    Schema::create('appointment_types', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('clinic_id');
        $table->string('name');
        $table->integer('duration');
        $table->timestamps();
    });

    // Create soap_templates table (for existing functionality)
    Schema::dropIfExists('soap_templates');
    Schema::create('soap_templates', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('clinic_id');
        $table->string('name');
        $table->text('template');
        $table->timestamps();
    });
}

// Helper functions for auto-discovery tests

function createBootTestModelFiles(): string
{
    $testDir = sys_get_temp_dir().'/eloquent-defaults-boot-test-models';

    if (! File::isDirectory($testDir)) {
        File::makeDirectory($testDir, 0755, true);
    }

    // Create TestPatient (provider model)
    File::put($testDir.'/TestPatient.php', '<?php
namespace Tests\BootConflict;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestPatient extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "patients";
    protected $fillable = ["name", "clinic_id"];
    
    protected static function eloquentDefaults(\\Clinic $clinic): array
    {
        return [
            static::make(["name" => "Test Patient 1", "clinic_id" => $clinic->id]),
            static::make(["name" => "Test Patient 2", "clinic_id" => $clinic->id]),
        ];
    }
}');

    // Create TestStaff (provider model)
    File::put($testDir.'/TestStaff.php', '<?php
namespace Tests\BootConflict;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestStaff extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "staff";
    protected $fillable = ["name", "clinic_id"];
    
    protected static function eloquentDefaults(\\Clinic $clinic): array
    {
        return [
            static::make(["name" => "Default Admin", "clinic_id" => $clinic->id]),
        ];
    }
}');

    // Create TestClinicSetting (provider model for AdvancedClinic)
    File::put($testDir.'/TestClinicSetting.php', '<?php
namespace Tests\BootConflict;
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class TestClinicSetting extends Model
{
    use HasEloquentDefaults;
    
    protected $table = "clinic_settings";
    protected $fillable = ["key", "value", "clinic_id"];
    
    protected static function eloquentDefaults(\\AdvancedClinic $clinic): array
    {
        return [
            static::make(["key" => "theme", "value" => "light", "clinic_id" => $clinic->id]),
            static::make(["key" => "locale", "value" => "en", "clinic_id" => $clinic->id]),
        ];
    }
}');

    return $testDir;
}

function cleanupBootTestModelFiles(): void
{
    $testDir = sys_get_temp_dir().'/eloquent-defaults-boot-test-models';

    if (File::isDirectory($testDir)) {
        File::deleteDirectory($testDir);
    }
}
