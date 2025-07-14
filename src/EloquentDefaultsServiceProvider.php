<?php

namespace dayemsiddiqui\EloquentDefaults;

use dayemsiddiqui\EloquentDefaults\Commands\EloquentDefaultsCommand;
use dayemsiddiqui\EloquentDefaults\Services\ModelDiscoveryService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EloquentDefaultsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('eloquent-defaults')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_eloquent_defaults_table')
            ->hasCommand(EloquentDefaultsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModelDiscoveryService::class);

        $this->app->singleton(EloquentDefaults::class, function ($app) {
            return new EloquentDefaults(
                $app->make(ModelDiscoveryService::class)
            );
        });
    }

    public function packageBooted(): void
    {
        // Models with HasEloquentDefaults trait will automatically register themselves
        // during their boot process, so no additional setup is needed here
    }
}
