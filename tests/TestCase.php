<?php

namespace dayemsiddiqui\EloquentDefaults\Tests;

use dayemsiddiqui\EloquentDefaults\EloquentDefaultsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'dayemsiddiqui\\EloquentDefaults\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EloquentDefaultsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();

        $schema->create('companies', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $schema->create('plans', function ($table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->integer('price');
            $table->timestamps();
        });

        $schema->create('roles', function ($table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->string('permissions');
            $table->timestamps();
        });

        $schema->create('charges', function ($table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->integer('amount');
            $table->timestamps();
        });

        $schema->create('circular_model_as', function ($table) {
            $table->id();
            $table->unsignedBigInteger('circular_model_b_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('circular_model_bs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('circular_model_a_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }
}
