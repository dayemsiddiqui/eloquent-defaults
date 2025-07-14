<?php

// Example usage of the new HasEloquentDefaults trait

namespace Examples;

use dayemsiddiqui\EloquentDefaults\Traits\HasEloquentDefaults;
use Illuminate\Database\Eloquent\Model;

// User model that will trigger defaults when created
class User extends Model
{
    protected $fillable = ['name', 'email'];
}

// Plan model that creates default plans when a User is created
class Plan extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['name', 'price', 'user_id'];

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Plan::make(['name' => 'Free Plan', 'price' => 0, 'user_id' => $user->id]),
            Plan::make(['name' => 'Pro Plan', 'price' => 10, 'user_id' => $user->id]),
        ];
    }
}

// Setting model that creates default settings when a User is created
class Setting extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['key', 'value', 'user_id'];

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Setting::make(['key' => 'theme', 'value' => 'light', 'user_id' => $user->id]),
            Setting::make(['key' => 'notifications', 'value' => 'enabled', 'user_id' => $user->id]),
            Setting::make(['key' => 'locale', 'value' => 'en', 'user_id' => $user->id]),
        ];
    }
}

// Profile model with dynamic defaults based on user data
class Profile extends Model
{
    use HasEloquentDefaults;

    protected $fillable = ['bio', 'avatar', 'user_id'];

    protected static function eloquentDefaults(User $user): array
    {
        return [
            Profile::make([
                'bio' => "Welcome {$user->name}! Thanks for joining our platform.",
                'avatar' => 'default-avatar.png',
                'user_id' => $user->id,
            ]),
        ];
    }
}

// Usage:
// When a user is created, all default models will be automatically created:
//
// $user = User::create([
//     'name' => 'John Doe',
//     'email' => 'john@example.com'
// ]);
//
// After creation:
// - 2 Plan records will be created
// - 3 Setting records will be created
// - 1 Profile record will be created
// - All with the correct user_id relationship

// Key Features:
// 1. Type safety with User parameter
// 2. IDE autocomplete and type checking
// 3. Automatic transaction handling
// 4. Multiple models can create defaults for the same trigger
// 5. Dynamic values based on the triggering model
// 6. Uses Model::make() for better control flow
