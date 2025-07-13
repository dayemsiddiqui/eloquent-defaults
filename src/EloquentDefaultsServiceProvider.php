<?php

namespace dayemsiddiqui\EloquentDefaults;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use dayemsiddiqui\EloquentDefaults\Commands\EloquentDefaultsCommand;

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
}
