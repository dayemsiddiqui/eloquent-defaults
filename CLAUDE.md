# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package called `eloquent-defaults` that provides an elegant way to automatically insert default rows to a table whenever a new row to a specific model is created. The package is built using Spatie's Laravel Package Tools framework.

## Development Commands

### Testing
- `composer test` - Run all tests using Pest
- `composer test-coverage` - Run tests with coverage report
- `vendor/bin/pest` - Direct Pest command

### Code Quality
- `composer analyse` - Run PHPStan static analysis
- `composer format` - Format code using Laravel Pint
- `vendor/bin/pint` - Direct Pint command
- `vendor/bin/phpstan analyse` - Direct PHPStan command

### Package Setup
- `composer run prepare` - Discover package for Testbench (runs automatically after autoload-dump)

## Architecture

### Package Structure
- **Main Class**: `src/EloquentDefaults.php` - Currently empty, main functionality placeholder
- **Service Provider**: `src/EloquentDefaultsServiceProvider.php` - Registers package configuration, views, migrations, and commands
- **Command**: `src/Commands/EloquentDefaultsCommand.php` - Basic Artisan command (`php artisan eloquent-defaults`)
- **Config**: `config/eloquent-defaults.php` - Empty configuration file ready for options
- **Tests**: Uses Pest testing framework with Orchestra Testbench for Laravel package testing

### Key Dependencies
- **Laravel**: ^10.0||^11.0||^12.0 (Illuminate contracts)
- **PHP**: ^8.4
- **Testing**: Pest with Laravel plugin and Arch testing
- **Code Quality**: PHPStan with Larastan, Laravel Pint for formatting
- **Package Tools**: Spatie Laravel Package Tools for scaffolding

### Package Registration
The package auto-registers via Laravel's package discovery with:
- Service Provider: `EloquentDefaultsServiceProvider`
- Facade: `EloquentDefaults`
- Artisan Command: `eloquent-defaults`

### Testing Setup
Tests extend `Orchestra\Testbench\TestCase` and automatically configure the package service provider. Factory guessing is set up for the package namespace.