# Eloquent Defaults

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dayemsiddiqui/eloquent-defaults.svg?style=flat-square)](https://packagist.org/packages/dayemsiddiqui/eloquent-defaults)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dayemsiddiqui/eloquent-defaults/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dayemsiddiqui/eloquent-defaults/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dayemsiddiqui/eloquent-defaults/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dayemsiddiqui/eloquent-defaults/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dayemsiddiqui/eloquent-defaults.svg?style=flat-square)](https://packagist.org/packages/dayemsiddiqui/eloquent-defaults)

An elegant Laravel package that enables Eloquent models to automatically seed default rows when a related "root" model is created. Perfect for multi-tenant applications, SaaS products, and any scenario where you need to populate related data automatically.

## The Problem

In Laravel applications, it's common to need pre-filled models with default data whenever a new parent/root model is created. This could include default plans for a company, default permissions for a role, or default tasks for a project. Most developers handle this with events, service classes, or observers — leading to boilerplate, poor discoverability, and scattered logic.

## The Solution

Eloquent Defaults provides a clean, declarative way to define what default data should be seeded when a root model is created. With a single trait and a couple of lines, your models can automatically create their default related records.


## Installation

You can install the package via composer:

```bash
composer require dayemsiddiqui/eloquent-defaults
```


## Quick Example

```php
use Illuminate\Database\Eloquent\Model;
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class Plan extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['company_id', 'name', 'price'];

    protected static function eloquentDefaults(Company $company): array
    {
        return [
            Plan::make(['company_id' => $company->id, 'name' => 'Basic Plan', 'price' => 999]),
            Plan::make(['company_id' => $company->id, 'name' => 'Pro Plan', 'price' => 1999]),
            Plan::make(['company_id' => $company->id, 'name' => 'Enterprise Plan', 'price' => 4999]),
        ];
    }
}
```

Now whenever a `Company` is created, three default plans will automatically be seeded!

```php
$company = Company::create(['name' => 'Acme Corp']);
// Automatically creates 3 plans for this company
```

## Features

- **Type Safe**: Full IDE support with typed parameters and generics
- **Model-Centric**: Each model defines its own defaults using familiar Laravel patterns
- **Uses Model::make()**: Better control flow and validation than raw arrays
- **Automatic**: No manual event binding or service provider registration required
- **Multi-Model Support**: Multiple models can create defaults for the same trigger
- **Zero Configuration**: Works out of the box with no config files
- **Transaction Safe**: All default creation happens within database transactions
- **Laravel 10-12 Compatible**: Built for modern Laravel applications

## Usage

### Basic Setup

1. Add the `HasEloquentDefaults` trait to your model
2. Implement the `eloquentDefaults()` method with a typed parameter
3. Return an array of models created with `::make()`

```php
use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;

class Role extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['company_id', 'name', 'permissions'];

    protected static function eloquentDefaults(Company $company): array
    {
        return [
            Role::make(['company_id' => $company->id, 'name' => 'Admin', 'permissions' => 'all']),
            Role::make(['company_id' => $company->id, 'name' => 'Editor', 'permissions' => 'read,write']),
            Role::make(['company_id' => $company->id, 'name' => 'Viewer', 'permissions' => 'read']),
        ];
    }
}
```

### Multiple Models for One Trigger

You can have multiple models create defaults for the same trigger model:

```php
// All of these will create defaults when a Company is created
class Plan extends Model { 
    use HasEloquentDefaults; 
    protected static function eloquentDefaults(Company $company): array { /* ... */ }
}
class Role extends Model { 
    use HasEloquentDefaults; 
    protected static function eloquentDefaults(Company $company): array { /* ... */ }
}
class Setting extends Model { 
    use HasEloquentDefaults; 
    protected static function eloquentDefaults(Company $company): array { /* ... */ }
}
```

### Advanced Examples

#### Dynamic Defaults Based on Model Data

```php
class Subscription extends Model
{
    use HasEloquentDefaults;

    protected static function eloquentDefaults(User $user): array
    {
        $defaults = [
            Subscription::make(['user_id' => $user->id, 'plan' => 'free']),
        ];

        // Add bonus subscription for verified users
        if ($user->email_verified_at) {
            $defaults[] = Subscription::make([
                'user_id' => $user->id, 
                'plan' => 'verified_bonus',
                'expires_at' => now()->addDays(30)
            ]);
        }

        return $defaults;
    }
}
```

#### Complex Relationships

```php
class Profile extends Model
{
    use HasEloquentDefaults;

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Profile::make([
                'user_id' => $user->id,
                'bio' => "Welcome {$user->name}! Thanks for joining our platform.",
                'avatar' => 'default-avatar.png',
                'preferences' => json_encode([
                    'theme' => 'light',
                    'notifications' => true,
                    'locale' => 'en'
                ])
            ]),
        ];
    }
}
```

### Debugging

View all registered model relationships:

```php
use dayemsiddiqui\EloquentDefaults\Facades\EloquentDefaults;

EloquentDefaults::debugRegistrations();
// Returns array of all target models and their provider models
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Dayem Siddiqui](https://github.com/dayemsiddiqui)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
