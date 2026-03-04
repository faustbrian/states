A flexible state management package for Laravel that allows any model or object to have multiple states with full transition history, context isolation, and environment support.

## Requirements

States requires PHP 8.4+ and Laravel/Eloquent 11+.

## Installation in Laravel

Install States with composer:

```bash
composer require cline/states
```

## Run Migrations

First publish the migrations into your app's `migrations` directory:

```bash
php artisan vendor:publish --tag="states-migrations"
```

Then run the migrations:

```bash
php artisan migrate
```

## Add the Trait

Add States' trait to your models:

```php
use Cline\States\Concerns\HasStates;

class User extends Model
{
    use HasStates;
}
```

## Using the Facade

Whenever you use the `States` facade in your code, remember to add this line to your namespace imports at the top of the file:

```php
use Cline\States\Facades\States;
```

## Quick Start

Once installed, you can assign states to any model:

```php
$user = User::find(1);

// Assign a state
States::for($user)->assign('active');

// Transition between states with full audit trail
States::for($user)
    ->by($admin)
    ->because('User completed email verification')
    ->transition('pending', 'active');

// Check if a user has a state
if (States::hasState($user, 'active')) {
    // User is active
}

// Get all states for a context
$states = States::getStates($user);

// Use namespaced states for organization
States::for($user)->assign('subscription.premium');
States::for($user)->assign('workflow.onboarding');
```

### State Scoping with Boundaries

Scope states to specific contexts using boundaries:

```php
$team = Team::find(1);

// Assign a role scoped to a team
States::for($user, boundary: $team)->assign('role.admin');

// Check role within team context
States::hasState($user, 'role.admin', boundary: $team); // true
States::hasState($user, 'role.admin'); // false (different boundary)
```

### Environment Isolation

Separate states across environments:

```php
// Production states
States::for($user, environment: 'production')->assign('active');

// Staging states (completely isolated)
States::for($user, environment: 'staging')->assign('testing');

// Multi-tenancy
$tenant = Tenant::current();
States::for($user, environment: "tenant_{$tenant->id}")->assign('premium');
```

### State Machines

Configure allowed transitions in `config/states.php`:

```php
return [
    'machines' => [
        'App\Models\User' => [
            'transitions' => [
                'pending' => ['active', 'rejected'],
                'active' => ['suspended', 'deleted'],
                'suspended' => ['active', 'deleted'],
            ],
        ],
    ],
];
```

Now transitions are validated:

```php
// Valid transition
States::for($user)->transition('pending', 'active'); // ✓

// Invalid transition throws TransitionNotAllowedByConfigurationException
States::for($user)->transition('pending', 'deleted'); // ✗
```

## Installation in Non-Laravel Apps

Install States with composer:

```bash
composer require cline/states
```

Set up the database with the Eloquent Capsule component:

```php
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([/* connection config */]);

$capsule->setAsGlobal();
```

Run the migrations by either using a tool like vagabond to run Laravel migrations outside of a Laravel app, or run the raw SQL directly in your database.

Add States' trait to your models:

```php
use Illuminate\Database\Eloquent\Model;
use Cline\States\Concerns\HasStates;

class User extends Model
{
    use HasStates;
}
```

Create an instance of StateManager:

```php
use Cline\States\StateManager;

$states = new StateManager();

// Assign states
$states->for($user)->assign('active');
```

If you're using dependency injection in your app, you may register the `StateManager` instance as a singleton in the container:

```php
use Cline\States\StateManager;
use Illuminate\Container\Container;

Container::getInstance()->singleton(StateManager::class, function () {
    return new StateManager();
});
```

You can now inject `StateManager` into any class that needs it.
