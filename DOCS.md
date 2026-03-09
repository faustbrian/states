## Table of Contents

1. [Getting Started](#doc-docs-readme) (`docs/README.md`)
2. [Basic Usage](#doc-docs-basic-usage) (`docs/basic-usage.md`)
3. [Contexts and Boundaries](#doc-docs-contexts-and-boundaries) (`docs/contexts-and-boundaries.md`)
4. [Environments](#doc-docs-environments) (`docs/environments.md`)
5. [Transition History](#doc-docs-transition-history) (`docs/transition-history.md`)
6. [State Machines](#doc-docs-state-machines) (`docs/state-machines.md`)
7. [Advanced Usage](#doc-docs-advanced-usage) (`docs/advanced-usage.md`)
8. [Configuration](#doc-docs-configuration) (`docs/configuration.md`)
<a id="doc-docs-readme"></a>

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

<a id="doc-docs-basic-usage"></a>

# Basic Usage

This guide covers the core operations for working with states: assigning, transitioning, removing, and checking states.

## Assigning States

Assign a state to any context:

```php
use Cline\States\Facades\States;

$user = User::find(1);

States::for($user)->assign('active');
```

### With Data

Store additional data with a state:

```php
States::for($user)->assign('subscription', [
    'tier' => 'premium',
    'expires_at' => now()->addYear(),
    'features' => ['api_access', 'priority_support'],
]);
```

### Namespaced States

Organize states using namespaces:

```php
// Workflow states
States::for($user)->assign('workflow.onboarding');
States::for($user)->assign('workflow.active');

// Role states
States::for($user)->assign('role.admin');
States::for($user)->assign('role.moderator');

// Feature flags
States::for($user)->assign('feature.beta_access');
```

## Transitioning States

Transition from one state to another with full audit trail:

```php
States::for($user)->transition('pending', 'active');
```

### With Actor and Reason

Record who made the change and why:

```php
$admin = User::find(2);

States::for($user)
    ->by($admin)
    ->because('User completed email verification')
    ->transition('pending', 'active');
```

### With Metadata

Include additional context:

```php
States::for($order)
    ->by($customer)
    ->because('Payment received')
    ->withMetadata([
        'payment_id' => 'pay_123',
        'amount' => 99.99,
        'gateway' => 'stripe',
    ])
    ->transition('pending', 'paid');
```

### Namespaced Transitions

Transitions work with namespaced states:

```php
States::for($user)
    ->by($admin)
    ->because('User upgraded subscription')
    ->transition('subscription.free', 'subscription.premium');
```

## Removing States

Remove a state from a context:

```php
States::for($user)->remove('active');
```

### With Audit Trail

Record who removed it and why:

```php
States::for($user)
    ->by($admin)
    ->because('Account suspended for policy violation')
    ->remove('active');
```

## Checking States

### Has State

Check if a context has a specific state:

```php
if (States::hasState($user, 'active')) {
    // User is active
}

// With namespaced states
if (States::hasState($user, 'role.admin')) {
    // User is admin
}
```

### Has All States

Check if a context has all of the specified states:

```php
if (States::hasAllStates($user, ['active', 'verified'])) {
    // User is both active and verified
}
```

### Has Any State

Check if a context has at least one of the specified states:

```php
if (States::hasAnyState($user, ['role.admin', 'role.moderator'])) {
    // User is either admin or moderator
}
```

### Using the Trait

If your model uses the `HasStates` trait, you can check states directly:

```php
if ($user->hasState('active')) {
    // User is active
}

if ($user->hasAllStates(['active', 'verified'])) {
    // User is both active and verified
}

if ($user->hasAnyState(['role.admin', 'role.moderator'])) {
    // User is either admin or moderator
}
```

## Retrieving States

### Get All States

Get all states for a context:

```php
$states = States::getStates($user);

foreach ($states as $state) {
    echo $state->getFullyQualifiedName(); // e.g., "workflow.active"
    echo $state->data; // Additional data if any
}
```

### Get States by Namespace

Filter states by namespace:

```php
// Get only workflow states
$workflowStates = States::getStates($user, namespace: 'workflow');

// Get only role states
$roleStates = States::getStates($user, namespace: 'role');
```

### Using the Trait

```php
// Get all states
$states = $user->states;

// Or use the query relationship
$workflowStates = $user->states()
    ->inNamespace('workflow')
    ->get();
```

## Working with State Data

### Accessing Data

```php
$state = States::getStates($user)->first();

// Get data as array
$data = $state->data;

// Access specific fields
$tier = $state->data['tier'] ?? null;
```

### Updating State Data

To update state data, you need to remove and reassign:

```php
// Get current data
$state = States::getStates($user, namespace: 'subscription')->first();
$currentData = $state->data;

// Update data
$newData = array_merge($currentData, [
    'tier' => 'enterprise',
    'updated_at' => now(),
]);

// Remove and reassign
States::for($user)->remove('subscription');
States::for($user)->assign('subscription', $newData);
```

## Querying States

Use the query builder for advanced queries:

```php
use Cline\States\Database\Models\State;

// Find all users with 'active' state
$activeUserIds = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->withState('active')
    ->pluck('context_id');

$activeUsers = User::query()->whereIn('id', $activeUserIds)->get();
```

### With Multiple States

```php
// Users who are both active AND premium
$contextIds = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->withState(['active', 'subscription.premium'])
    ->select('context_id')
    ->groupBy('context_id')
    ->havingRaw('COUNT(DISTINCT name) = 2')
    ->pluck('context_id');

$premiumActiveUsers = User::query()->whereIn('id', $contextIds)->get();
```

## Using with Eloquent Models

### Model Scopes

Create scopes for common queries:

```php
class User extends Model
{
    use HasStates;

    public function scopeWithState(Builder $query, string $state): Builder
    {
        $stateContexts = State::query()
            ->where('context_type', $this->getMorphClass())
            ->withState($state)
            ->pluck('context_id');

        return $query->whereIn('id', $stateContexts);
    }
}

// Usage
$activeUsers = User::withState('active')->get();
```

### Model Observers

Automatically assign states on model creation:

```php
class UserObserver
{
    public function created(User $user): void
    {
        States::for($user)->assign('pending');
    }

    public function deleting(User $user): void
    {
        if (States::hasState($user, 'active')) {
            States::for($user)
                ->by(auth()->user())
                ->because('Model deleted')
                ->transition('active', 'deleted');
        }
    }
}

// Register observer
User::observe(UserObserver::class);
```

## Best Practices

### 1. Use Meaningful State Names

```php
// Good
States::for($user)->assign('email_verified');
States::for($order)->assign('payment.completed');

// Bad
States::for($user)->assign('flag1');
States::for($order)->assign('state2');
```

### 2. Provide Context with Transitions

```php
// Good: Full audit trail
States::for($user)
    ->by($admin)
    ->because('User requested account deletion')
    ->withMetadata(['ticket_id' => '123'])
    ->transition('active', 'deleted');

// Bad: No context
States::for($user)->transition('active', 'deleted');
```

### 3. Use Namespaces for Organization

```php
// Good: Organized by domain
States::for($user)->assign('workflow.onboarding');
States::for($user)->assign('subscription.premium');
States::for($user)->assign('role.admin');

// Bad: Flat structure
States::for($user)->assign('onboarding');
States::for($user)->assign('premium');
States::for($user)->assign('admin');
```

### 4. Store Relevant Data

```php
// Good: Useful contextual data
States::for($user)->assign('subscription.premium', [
    'tier' => 'gold',
    'started_at' => now(),
    'billing_cycle' => 'annual',
]);

// Bad: Redundant or useless data
States::for($user)->assign('active', [
    'is_active' => true, // Redundant
    'foo' => 'bar', // Meaningless
]);
```

## Next Steps

- [Contexts and Boundaries](#doc-docs-contexts-and-boundaries) - Scope states to specific contexts
- [Environments](#doc-docs-environments) - Separate states across environments
- [Transition History](#doc-docs-transition-history) - Track and analyze state changes
- [State Machines](#doc-docs-state-machines) - Validate allowed transitions

<a id="doc-docs-contexts-and-boundaries"></a>

# Contexts and Boundaries

States can be scoped to specific contexts (the object that has states) and optional boundaries (additional scoping for multi-tenancy, teams, projects, etc.).

## Understanding Contexts

A **context** is any object that can have states. Typically these are Eloquent models:

```php
$user = User::find(1);
$order = Order::find(100);
$document = Document::find(50);

// Each context has its own states
States::for($user)->assign('active');
States::for($order)->assign('pending');
States::for($document)->assign('draft');
```

## Understanding Boundaries

A **boundary** provides additional scoping for states. This allows the same context to have different states in different boundaries:

```php
$user = User::find(1);
$teamA = Team::find(1);
$teamB = Team::find(2);

// User has different roles in different teams
States::for($user, boundary: $teamA)->assign('role.admin');
States::for($user, boundary: $teamB)->assign('role.member');

// Check states within boundaries
States::hasState($user, 'role.admin', boundary: $teamA); // true
States::hasState($user, 'role.admin', boundary: $teamB); // false
```

## Common Use Cases

### 1. Team Memberships

Manage user roles within teams:

```php
$user = User::find(1);
$team = Team::find(1);

// Assign role within team
States::for($user, boundary: $team)->assign('role.admin');

// Check permissions
if (States::hasState($user, 'role.admin', boundary: $team)) {
    // User is admin of this team
}

// Get all roles within team
$teamRoles = States::getStates($user, namespace: 'role', boundary: $team);
```

### 2. Project Assignments

Track user status across different projects:

```php
$developer = User::find(1);
$projectA = Project::find(1);
$projectB = Project::find(2);

// Assign different states in different projects
States::for($developer, boundary: $projectA)->assign('status.lead');
States::for($developer, boundary: $projectB)->assign('status.contributor');

// Check status in specific project
if (States::hasState($developer, 'status.lead', boundary: $projectA)) {
    // Developer leads project A
}
```

### 3. Multi-Tenancy

Isolate states per tenant:

```php
$user = User::find(1);
$tenantA = Tenant::find(1);
$tenantB = Tenant::find(2);

// User has subscription in tenant A
States::for($user, boundary: $tenantA)->assign('subscription.premium');

// User has different subscription in tenant B
States::for($user, boundary: $tenantB)->assign('subscription.free');

// Check subscription per tenant
States::hasState($user, 'subscription.premium', boundary: $tenantA); // true
States::hasState($user, 'subscription.premium', boundary: $tenantB); // false
```

### 4. Organization Hierarchies

Model complex organizational structures:

```php
$employee = User::find(1);
$engineering = Department::find(1);
$product = Department::find(2);

// Employee has different levels in different departments
States::for($employee, boundary: $engineering)->assign('level.senior');
States::for($employee, boundary: $product)->assign('level.principal');

// Transition levels within specific department
States::for($employee, boundary: $engineering)
    ->by($manager)
    ->because('Annual review - promotion approved')
    ->transition('level.senior', 'level.staff');
```

### 5. Resource Access

Control access to specific resources:

```php
$user = User::find(1);
$documentA = Document::find(1);
$documentB = Document::find(2);

// Grant different access levels per document
States::for($user, boundary: $documentA)->assign('access.editor');
States::for($user, boundary: $documentB)->assign('access.viewer');

// Check access
if (States::hasState($user, 'access.editor', boundary: $document)) {
    // User can edit this document
}
```

## Working Without Boundaries

If you don't specify a boundary, states are scoped only to the context:

```php
$user = User::find(1);

// Global user states (no boundary)
States::for($user)->assign('email_verified');
States::for($user)->assign('onboarding_complete');

// These are separate from boundary-scoped states
States::hasState($user, 'email_verified'); // true
States::hasState($user, 'email_verified', boundary: $team); // false
```

## Querying Across Boundaries

### Find All States for a Context

```php
// Get all states for user, regardless of boundary
$allStates = States::getStates($user);

// Get all states within a specific boundary
$teamStates = States::getStates($user, boundary: $team);

// Get all states with no boundary (global states)
$globalStates = States::getStates($user, boundary: null);
```

### Find Contexts by State and Boundary

```php
use Cline\States\Database\Models\State;

$team = Team::find(1);

// Find all users with admin role in this team
$adminIds = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->where('boundary_type', $team->getMorphClass())
    ->where('boundary_id', $team->getKey())
    ->withState('role.admin')
    ->pluck('context_id');

$teamAdmins = User::query()->whereIn('id', $adminIds)->get();
```

## Combining Boundaries with Environments

Boundaries and environments work together for maximum isolation:

```php
$user = User::find(1);
$team = Team::find(1);

// Production: user is admin in team
States::for($user, boundary: $team, environment: 'production')
    ->assign('role.admin');

// Staging: user is member in same team
States::for($user, boundary: $team, environment: 'staging')
    ->assign('role.member');

// Check with full context
States::hasState($user, 'role.admin',
    boundary: $team,
    environment: 'production'
); // true

States::hasState($user, 'role.admin',
    boundary: $team,
    environment: 'staging'
); // false
```

## Custom Context Objects

Contexts don't have to be Eloquent models. Any object implementing `HasStateContext` can have states:

```php
use Cline\States\Contracts\HasStateContext;

class CustomContext implements HasStateContext
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function getStateContextType(): string
    {
        return self::class;
    }

    public function getStateContextId(): string|int
    {
        return $this->id;
    }
}

// Use custom context
$context = new CustomContext('ctx_123');
States::for($context)->assign('active');
```

## Best Practices

### 1. Choose Appropriate Boundaries

```php
// Good: Meaningful boundaries
States::for($user, boundary: $team)->assign('role.admin');
States::for($user, boundary: $project)->assign('status.active');

// Bad: Unnecessary boundaries
States::for($user, boundary: $user)->assign('email_verified'); // Just use no boundary
```

### 2. Be Consistent with Scoping

```php
// Good: Consistent scoping strategy
States::for($user, boundary: $team)->assign('role.admin');
States::for($user, boundary: $team)->assign('permissions.billing');

// Bad: Mixing scoped and unscoped
States::for($user, boundary: $team)->assign('role.admin');
States::for($user)->assign('permissions.billing'); // Inconsistent
```

### 3. Document Your Boundary Strategy

```php
/**
 * Team Member States:
 *
 * - All team-related states MUST use team as boundary
 * - Roles: role.{admin,member,guest}
 * - Permissions: permission.{manage_billing,view_analytics}
 * - Status: status.{active,invited,suspended}
 */
class TeamMember
{
    public function assignRole(User $user, Team $team, string $role): void
    {
        States::for($user, boundary: $team)->assign("role.{$role}");
    }
}
```

### 4. Clean Up Boundary States

Remove states when boundaries are deleted:

```php
class TeamObserver
{
    public function deleting(Team $team): void
    {
        // Clean up all states scoped to this team
        State::query()
            ->where('boundary_type', $team->getMorphClass())
            ->where('boundary_id', $team->getKey())
            ->delete();
    }
}
```

## Next Steps

- [Environments](#doc-docs-environments) - Separate states across environments
- [Transition History](#doc-docs-transition-history) - Track changes with full context
- [Advanced Usage](#doc-docs-advanced-usage) - Complex patterns and queries

<a id="doc-docs-environments"></a>

# Environments

Environments provide complete isolation of states. States in one environment are completely separate from states in another environment, allowing you to maintain parallel state systems for the same objects.

## Basic Usage

### Default Environment

By default, all states use the `'default'` environment:

```php
$user = User::find(1);

// These are equivalent
States::for($user)->assign('active');
States::for($user, environment: 'default')->assign('active');
```

### Explicit Environments

Specify an environment for complete isolation:

```php
// Production states
States::for($user, environment: 'production')->assign('active');

// Staging states (completely separate)
States::for($user, environment: 'staging')->assign('testing');

// Check states
States::hasState($user, 'active', environment: 'production'); // true
States::hasState($user, 'testing', environment: 'staging'); // true
States::hasState($user, 'active', environment: 'staging'); // false
```

## Common Use Cases

### 1. Multi-Tenancy

Isolate states per tenant:

```php
$tenant = Tenant::current();
$user = User::find(1);

// Each tenant has completely isolated states
States::for($user, environment: "tenant_{$tenant->id}")
    ->assign('subscription.premium');

// Different tenant, different states
$otherTenant = Tenant::find(2);
States::for($user, environment: "tenant_{$otherTenant->id}")
    ->assign('subscription.free');
```

### 2. Testing Isolation

Keep test states separate from production:

```php
class UserTest extends TestCase
{
    public function test_user_activation(): void
    {
        $user = User::factory()->create();

        // Use test environment
        States::for($user, environment: 'testing')
            ->assign('pending');

        States::for($user, environment: 'testing')
            ->transition('pending', 'active');

        $this->assertTrue(
            States::hasState($user, 'active', environment: 'testing')
        );

        // Production environment unaffected
        $this->assertFalse(
            States::hasState($user, 'active', environment: 'production')
        );
    }
}
```

### 3. Draft vs Published States

Maintain parallel draft and published state systems:

```php
$document = Document::find(1);

// Published version state
States::for($document, environment: 'published')
    ->assign('approved');

// Draft version state (independent)
States::for($document, environment: 'draft')
    ->assign('in-review');

// Promote draft to published
$draftStates = States::getStates($document, environment: 'draft');

foreach ($draftStates as $state) {
    States::for($document, environment: 'published')
        ->assign($state->getFullyQualifiedName(), $state->data);
}
```

### 4. Preview/Simulation Modes

Run "what-if" scenarios without affecting production:

```php
$order = Order::find(1);

// Production state
States::for($order, environment: 'production')
    ->assign('pending');

// Simulate order processing in preview environment
States::for($order, environment: 'preview')
    ->assign('pending');

States::for($order, environment: 'preview')
    ->transition('pending', 'processing');

States::for($order, environment: 'preview')
    ->transition('processing', 'completed');

// Check what would happen without affecting production
$previewState = States::getStates($order, environment: 'preview')
    ->first();
// State is 'completed' in preview, still 'pending' in production
```

## Combining with Boundaries

Environments and boundaries work together:

```php
$user = User::find(1);
$team = Team::find(1);

// Production: user is admin in team
States::for($user, boundary: $team, environment: 'production')
    ->assign('role.admin');

// Staging: user is member in same team
States::for($user, boundary: $team, environment: 'staging')
    ->assign('role.member');

// Completely isolated
States::hasState($user, 'role.admin', boundary: $team, environment: 'production'); // true
States::hasState($user, 'role.member', boundary: $team, environment: 'staging'); // true
States::hasState($user, 'role.admin', boundary: $team, environment: 'staging'); // false
```

## Retrieving States by Environment

### Get States for Specific Environment

```php
$productionStates = States::getStates($user, environment: 'production');
$stagingStates = States::getStates($user, environment: 'staging');
```

### Query Across Environments

```php
use Cline\States\Database\Models\State;

// Get all environments for a context
$environments = State::query()
    ->forContext($user)
    ->distinct('environment')
    ->pluck('environment');

// Get states across all environments
$allStates = State::query()
    ->forContext($user)
    ->get()
    ->groupBy('environment');
```

## Transition History per Environment

Each environment maintains its own transition history:

```php
$user = User::find(1);

// Production transitions
States::for($user, environment: 'production')
    ->by($admin)
    ->because('Account activated')
    ->transition('pending', 'active');

// Staging transitions (independent)
States::for($user, environment: 'staging')
    ->by($tester)
    ->because('Testing activation flow')
    ->transition('pending', 'active');

// View production history
$prodState = States::getStates($user, environment: 'production')->first();
foreach ($prodState->transitions as $transition) {
    // Only production transitions
}

// View staging history
$stagingState = States::getStates($user, environment: 'staging')->first();
foreach ($stagingState->transitions as $transition) {
    // Only staging transitions
}
```

## Environment Helpers

### Create Environment-Scoped Helper

```php
class StateEnvironment
{
    public function __construct(
        private readonly string $environment,
    ) {}

    public function for(
        HasStateContext $context,
        ?HasStateContext $boundary = null,
    ): TransitionConductor {
        return States::for($context, $boundary, $this->environment);
    }

    public function hasState(
        HasStateContext $context,
        string $state,
        ?HasStateContext $boundary = null,
    ): bool {
        return States::hasState($context, $state, $this->environment, $boundary);
    }
}

// Usage
$production = new StateEnvironment('production');
$staging = new StateEnvironment('staging');

$production->for($user)->assign('active');
$staging->for($user)->assign('testing');
```

### Tenant-Scoped States

```php
trait HasTenantStates
{
    protected function tenantEnvironment(): string
    {
        return "tenant_" . Tenant::current()->id;
    }

    public function assignTenantState(string $state, ?array $data = null): State
    {
        return States::for($this, environment: $this->tenantEnvironment())
            ->assign($state, $data);
    }

    public function hasTenantState(string $state): bool
    {
        return States::hasState($this, $state, environment: $this->tenantEnvironment());
    }
}

class User extends Model
{
    use HasStates, HasTenantStates;
}

// Usage
$user->assignTenantState('subscription.premium');
$user->hasTenantState('subscription.premium'); // true
```

## Best Practices

### 1. Use Meaningful Environment Names

```php
// Good: Clear purpose
'production'
'staging'
'testing'
"tenant_{$tenant->id}"
'draft'
'published'

// Bad: Cryptic
'env1'
'temp'
'test123'
```

### 2. Document Your Environment Strategy

```php
/**
 * State Environments:
 *
 * - production: Live user states
 * - staging: Pre-production testing
 * - tenant_{id}: Multi-tenant isolation
 */
class User extends Model
{
    use HasStates;
}
```

### 3. Isolate Test Data

Always use separate environments for tests:

```php
class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Override default environment for tests
        config(['states.default_environment' => 'testing']);
    }
}
```

### 4. Environment Cleanup

Remember to clean up environments when no longer needed:

```php
// Delete all states for a specific environment
State::query()
    ->where('environment', 'preview_123')
    ->delete();

// Or delete with transitions
State::query()
    ->where('environment', 'preview_123')
    ->each(fn ($state) => $state->delete());
```

## Next Steps

- [Transition History](#doc-docs-transition-history) - Track changes per environment
- [State Machines](#doc-docs-state-machines) - Validate transitions per environment
- [Advanced Usage](#doc-docs-advanced-usage) - Complex multi-environment patterns

<a id="doc-docs-transition-history"></a>

# Transition History

Every state change is recorded in the `state_transitions` table, providing a complete audit trail of all state changes with context about who, what, when, why, and how.

## Understanding Transitions

A transition record contains:

- **`from_state`** - The previous state (null for initial assignments)
- **`to_state`** - The new state (`__removed__` for removals)
- **`actor_type`** / **`actor_id`** - Who made the change (optional)
- **`reason`** - Why the change was made (optional)
- **`metadata`** - Additional context as JSON (optional)
- **`created_at`** - When the change occurred
- **`state_id`** - Which state this transition belongs to

## Recording Transitions

### Basic Transition

```php
States::for($user)->assign('active');
// Creates transition: null → 'active'
```

### With Actor

```php
$admin = User::find(2);

States::for($user)
    ->by($admin)
    ->transition('pending', 'active');
// Records who made the change
```

### With Reason

```php
States::for($user)
    ->by($admin)
    ->because('Email verification completed')
    ->transition('pending', 'active');
// Records why the change was made
```

### With Metadata

```php
States::for($order)
    ->by($customer)
    ->because('Payment received')
    ->withMetadata([
        'payment_id' => 'pay_123',
        'amount' => 99.99,
        'currency' => 'USD',
        'gateway' => 'stripe',
    ])
    ->transition('pending', 'paid');
// Records additional context
```

### Complete Audit Trail

```php
States::for($document)
    ->by(auth()->user())
    ->because('Legal review completed - all clauses approved')
    ->withMetadata([
        'reviewer_id' => $reviewer->id,
        'review_notes' => 'Terms updated to include new privacy clauses',
        'approval_date' => now(),
        'document_version' => '2.1',
    ])
    ->transition('draft', 'approved');
```

## Retrieving Transitions

### Get All Transitions for a State

```php
$state = States::getStates($user)->first();

foreach ($state->transitions as $transition) {
    echo "{$transition->from_state} → {$transition->to_state}\n";
    echo "By: {$transition->actor_type}#{$transition->actor_id}\n";
    echo "Reason: {$transition->reason}\n";
    echo "When: {$transition->created_at}\n";
}
```

### Get the Most Recent Transition

```php
$latestTransition = $state->transitions()
    ->latest()
    ->first();

echo "Last changed at: {$latestTransition->created_at}";
echo "Changed by: {$latestTransition->actor->name}";
```

### Query Transitions Directly

```php
use Cline\States\Database\Models\StateTransition;

$transitions = StateTransition::query()
    ->where('state_id', $state->id)
    ->orderBy('created_at', 'desc')
    ->get();
```

## Querying Transition History

### Find Transitions by Actor

```php
$admin = User::find(1);

$adminTransitions = StateTransition::query()
    ->where('actor_type', $admin->getMorphClass())
    ->where('actor_id', $admin->getKey())
    ->get();

// Or using morph relation
$adminTransitions = StateTransition::query()
    ->whereHasMorph('actor', [User::class], function ($query) use ($admin) {
        $query->where('id', $admin->id);
    })
    ->get();
```

### Find Specific Transitions

```php
// All transitions to 'active' state
$activations = StateTransition::query()
    ->where('to_state', 'active')
    ->get();

// All transitions from 'pending' to 'active'
$pendingToActive = StateTransition::query()
    ->where('from_state', 'pending')
    ->where('to_state', 'active')
    ->get();

// All removals
$removals = StateTransition::query()
    ->where('to_state', '__removed__')
    ->get();
```

### Timeframe Queries

```php
// Transitions in last 24 hours
$recent = StateTransition::query()
    ->where('created_at', '>=', now()->subDay())
    ->get();

// Transitions between dates
$range = StateTransition::query()
    ->whereBetween('created_at', [
        now()->startOfMonth(),
        now()->endOfMonth(),
    ])
    ->get();
```

### Metadata Queries

```php
// Find by metadata
$stripePayments = StateTransition::query()
    ->where('metadata->gateway', 'stripe')
    ->get();

$highValue = StateTransition::query()
    ->where('metadata->amount', '>', 1000)
    ->get();
```

## Analyzing Transition Patterns

### State Change Timeline

```php
function getStateTimeline(HasStateContext $context): array
{
    $states = States::getStates($context);
    $timeline = [];

    foreach ($states as $state) {
        foreach ($state->transitions as $transition) {
            $timeline[] = [
                'timestamp' => $transition->created_at,
                'from' => $transition->from_state,
                'to' => $transition->to_state,
                'actor' => $transition->actor?->name ?? 'System',
                'reason' => $transition->reason,
            ];
        }
    }

    return collect($timeline)
        ->sortBy('timestamp')
        ->values()
        ->all();
}

$timeline = getStateTimeline($user);
/*
[
    ['timestamp' => '2024-01-01 10:00:00', 'from' => null, 'to' => 'pending', ...],
    ['timestamp' => '2024-01-02 14:30:00', 'from' => 'pending', 'to' => 'active', ...],
    ['timestamp' => '2024-01-15 09:15:00', 'from' => 'active', 'to' => 'suspended', ...],
]
*/
```

### Transition Statistics

```php
// Count transitions per state
$stats = StateTransition::query()
    ->selectRaw('to_state, COUNT(*) as count')
    ->groupBy('to_state')
    ->get();

// Average time in each state
function averageTimeInState(string $stateName): int
{
    $transitions = StateTransition::query()
        ->where('from_state', $stateName)
        ->get();

    $times = [];
    foreach ($transitions as $transition) {
        $entered = StateTransition::query()
            ->where('to_state', $stateName)
            ->where('state_id', $transition->state_id)
            ->where('created_at', '<', $transition->created_at)
            ->latest()
            ->first();

        if ($entered) {
            $times[] = $transition->created_at->diffInSeconds($entered->created_at);
        }
    }

    return $times ? (int) collect($times)->average() : 0;
}
```

### Actor Activity

```php
// Most active actors
$topActors = StateTransition::query()
    ->selectRaw('actor_type, actor_id, COUNT(*) as transitions')
    ->whereNotNull('actor_type')
    ->groupBy(['actor_type', 'actor_id'])
    ->orderByDesc('transitions')
    ->limit(10)
    ->get();
```

## Transition Events

Listen for state transitions using Laravel events:

```php
use Cline\States\Events\StateTransitioned;

class SendActivationEmail
{
    public function handle(StateTransitioned $event): void
    {
        if ($event->transition->to_state === 'active') {
            // Send email
        }
    }
}

// In EventServiceProvider
protected $listen = [
    StateTransitioned::class => [
        SendActivationEmail::class,
    ],
];
```

### Event Properties

The `StateTransitioned` event contains:

```php
class StateTransitioned
{
    public function __construct(
        public readonly HasStateContext $context,
        public readonly State $state,
        public readonly StateTransition $transition,
        public readonly ?HasStateContext $actor,
    ) {}
}
```

Usage:

```php
public function handle(StateTransitioned $event): void
{
    $user = $event->context; // The User model
    $state = $event->state; // The State model
    $transition = $event->transition; // The StateTransition model
    $admin = $event->actor; // Who made the change
}
```

## Cleaning Up History

### Prune Old Transitions

```php
// Delete transitions older than 90 days
StateTransition::query()
    ->where('created_at', '<', now()->subDays(90))
    ->delete();
```

### Archive Transitions

```php
// Move old transitions to archive table
DB::transaction(function () {
    $oldTransitions = StateTransition::query()
        ->where('created_at', '<', now()->subYear())
        ->get();

    foreach ($oldTransitions as $transition) {
        DB::table('state_transitions_archive')->insert(
            $transition->toArray()
        );
        $transition->delete();
    }
});
```

### Soft Delete Support

Add soft deletes to preserve transition history:

```php
// In a migration
Schema::table('state_transitions', function (Blueprint $table) {
    $table->softDeletes();
});

// Update model
use Illuminate\Database\Eloquent\SoftDeletes;

class StateTransition extends Model
{
    use SoftDeletes;
}

// Now deletes are soft by default
$transition->delete(); // Soft deleted
$transition->forceDelete(); // Permanent
```

## Best Practices

### 1. Always Provide Context

```php
// Good: Full context
States::for($user)
    ->by($admin)
    ->because('User requested account deletion')
    ->withMetadata(['ticket_id' => '123'])
    ->transition('active', 'deleted');

// Bad: No context
States::for($user)->transition('active', 'deleted');
```

### 2. Use Meaningful Reasons

```php
// Good: Clear and informative
->because('Payment failed after 3 retry attempts')
->because('User verified email address via confirmation link')
->because('Admin override: emergency account restoration')

// Bad: Vague
->because('changed')
->because('update')
```

### 3. Structure Metadata Consistently

```php
// Good: Consistent structure
->withMetadata([
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'request_id' => request()->id(),
])

// Bad: Inconsistent
->withMetadata(['ip' => '1.2.3.4'])
->withMetadata(['user_ip_addr' => '1.2.3.4', 'ua' => 'Mozilla'])
```

## Next Steps

- [State Machines](#doc-docs-state-machines) - Validate transitions before recording
- [Advanced Usage](#doc-docs-advanced-usage) - Complex transition patterns
- [Configuration](#doc-docs-configuration) - Configure transition tracking

<a id="doc-docs-state-machines"></a>

# State Machines

State machines allow you to define and enforce valid state transitions for your contexts. This prevents invalid state changes and ensures your objects follow defined workflows.

## Configuring State Machines

State machines are configured in `config/states.php`:

```php
return [
    'machines' => [
        // Context type => configuration
        'App\\Models\\User' => [
            'transitions' => [
                // from_state => [allowed_to_states]
                'pending' => ['active', 'rejected'],
                'active' => ['suspended', 'deleted'],
                'suspended' => ['active', 'deleted'],
                'rejected' => ['pending'], // Can retry
            ],
        ],

        'App\\Models\\Order' => [
            'transitions' => [
                'pending' => ['processing', 'cancelled'],
                'processing' => ['completed', 'failed', 'cancelled'],
                'failed' => ['pending', 'cancelled'],
                'completed' => [], // Terminal state
                'cancelled' => [], // Terminal state
            ],
        ],
    ],
];
```

## How It Works

When a state machine is configured for a context type:

1. **Assignment** - No validation, any state can be assigned
2. **Transition** - Validated against configured rules
3. **Removal** - No validation, any state can be removed

```php
$user = User::find(1);

// Assign works (no validation)
States::for($user)->assign('pending');

// Valid transition
States::for($user)->transition('pending', 'active'); // ✓ Allowed

// Invalid transition throws exception
States::for($user)->transition('pending', 'suspended'); // ✗ Not allowed
// TransitionNotAllowedByConfigurationException
```

## Exception Handling

```php
use Cline\States\Exceptions\TransitionNotAllowedByConfigurationException;

try {
    States::for($user)->transition('pending', 'deleted');
} catch (TransitionNotAllowedByConfigurationException $e) {
    // Handle invalid transition
    logger()->warning('Invalid state transition attempted', [
        'user_id' => $user->id,
        'from' => 'pending',
        'to' => 'deleted',
    ]);
}
```

## Common Patterns

### Linear Workflow

Simple progression through stages:

```php
'App\\Models\\Document' => [
    'transitions' => [
        'draft' => ['review'],
        'review' => ['approved', 'rejected'],
        'approved' => ['published'],
        'rejected' => ['draft'], // Back to editing
        'published' => [], // Terminal
    ],
],
```

Usage:

```php
States::for($doc)->assign('draft');
States::for($doc)->transition('draft', 'review');
States::for($doc)->transition('review', 'approved');
States::for($doc)->transition('approved', 'published');
// Cannot transition from 'published' - it's terminal
```

### Branching Workflow

Multiple paths from each state:

```php
'App\\Models\\SupportTicket' => [
    'transitions' => [
        'new' => ['assigned', 'closed'],
        'assigned' => ['in_progress', 'reassigned', 'closed'],
        'in_progress' => ['pending_customer', 'resolved', 'escalated'],
        'pending_customer' => ['in_progress', 'closed'],
        'escalated' => ['in_progress', 'resolved'],
        'resolved' => ['closed', 'reopened'],
        'reassigned' => ['assigned'],
        'reopened' => ['assigned'],
        'closed' => [], // Terminal
    ],
],
```

### Reversible States

Allow moving backward:

```php
'App\\Models\\Article' => [
    'transitions' => [
        'draft' => ['review', 'published'],
        'review' => ['draft', 'published'], // Can go back to draft
        'published' => ['draft', 'archived'], // Can unpublish
        'archived' => ['published', 'deleted'], // Can restore
    ],
],
```

### Approval Workflows

Multi-stage approvals:

```php
'App\\Models\\PurchaseOrder' => [
    'transitions' => [
        'submitted' => ['manager_review', 'cancelled'],
        'manager_review' => ['finance_review', 'rejected', 'cancelled'],
        'finance_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['processing'],
        'processing' => ['completed', 'cancelled'],
        'rejected' => ['submitted'], // Can resubmit
        'completed' => [],
        'cancelled' => [],
    ],
],
```

## Namespaced States

State machines work with namespaced states:

```php
'App\\Models\\Employee' => [
    'transitions' => [
        // Workflow states
        'workflow.onboarding' => ['workflow.active'],
        'workflow.active' => ['workflow.offboarding'],
        'workflow.offboarding' => [], // Terminal

        // Status states (separate machine)
        'status.part_time' => ['status.full_time', 'status.contractor'],
        'status.full_time' => ['status.part_time', 'status.contractor'],
        'status.contractor' => ['status.part_time', 'status.full_time'],
    ],
],
```

## Checking Allowed Transitions

### Get Allowed Next States

```php
function getAllowedTransitions(HasStateContext $context, string $currentState): array
{
    $config = config('states.machines');
    $contextType = $context->getStateContextType();

    if (!isset($config[$contextType]['transitions'][$currentState])) {
        return []; // No machine configured or terminal state
    }

    return $config[$contextType]['transitions'][$currentState];
}

$user = User::find(1);
$currentState = 'pending';

$allowed = getAllowedTransitions($user, $currentState);
// ['active', 'rejected']
```

### Check if Transition is Valid

```php
function canTransition(
    HasStateContext $context,
    string $from,
    string $to
): bool {
    $allowed = getAllowedTransitions($context, $from);
    return in_array($to, $allowed, true);
}

if (canTransition($user, 'pending', 'active')) {
    States::for($user)->transition('pending', 'active');
}
```

## Dynamic State Machines

### Runtime Configuration

Override configuration at runtime:

```php
// Not recommended for production, but useful for testing
config([
    'states.machines.App\\Models\\User.transitions' => [
        'pending' => ['active'],
        'active' => ['deleted'],
    ],
]);
```

### Per-Context Machines

Implement custom validation per instance:

```php
class Order extends Model implements HasStateContext
{
    use HasStates;

    public function getAllowedTransitions(string $from): array
    {
        // Custom logic per order
        if ($this->total_amount > 1000) {
            // High-value orders need extra approvals
            return [
                'pending' => ['manager_review'],
                'manager_review' => ['director_review'],
                'director_review' => ['approved', 'rejected'],
            ][$from] ?? [];
        }

        // Standard flow
        return [
            'pending' => ['approved', 'rejected'],
        ][$from] ?? [];
    }
}
```

## Conditional Transitions

Validate transitions with custom logic:

```php
class TransitionValidator
{
    public function validate(
        HasStateContext $context,
        string $from,
        string $to
    ): void {
        // Check configuration first
        if (!canTransition($context, $from, $to)) {
            throw new TransitionNotAllowedByConfigurationException($from, $to);
        }

        // Additional business rules
        if ($to === 'approved' && !$context->hasRequiredDocuments()) {
            throw new \RuntimeException('Cannot approve: missing required documents');
        }

        if ($to === 'published' && !$context->review_completed_at) {
            throw new \RuntimeException('Cannot publish: review not completed');
        }
    }
}

// Usage
$validator = new TransitionValidator();
$validator->validate($document, 'draft', 'published');

States::for($document)->transition('draft', 'published');
```

## Disabling Validation

Skip validation for specific operations:

```php
// Method 1: Remove from config
$config = config('states.machines');
unset($config['App\\Models\\User']);
config(['states.machines' => $config]);

// Method 2: Catch exception
try {
    States::for($user)->transition('pending', 'deleted');
} catch (TransitionNotAllowedByConfigurationException $e) {
    // Force transition anyway
    States::for($user)->remove('pending');
    States::for($user)->assign('deleted');
}
```

## Testing State Machines

```php
class UserStateMachineTest extends TestCase
{
    public function test_valid_transitions(): void
    {
        $user = User::factory()->create();

        States::for($user)->assign('pending');

        // Valid transition
        States::for($user)->transition('pending', 'active');
        $this->assertTrue(States::hasState($user, 'active'));
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $user = User::factory()->create();

        States::for($user)->assign('pending');

        $this->expectException(TransitionNotAllowedByConfigurationException::class);

        // Invalid transition
        States::for($user)->transition('pending', 'deleted');
    }

    public function test_terminal_states(): void
    {
        $user = User::factory()->create();

        States::for($user)->assign('deleted');

        // No transitions allowed from terminal state
        $this->expectException(TransitionNotAllowedByConfigurationException::class);
        States::for($user)->transition('deleted', 'active');
    }
}
```

## Best Practices

### 1. Design State Machines Upfront

Plan your workflows before implementation:

```
[draft] → [review] → [approved] → [published]
   ↓                     ↓
[archived]           [archived]
```

### 2. Use Terminal States

Clearly mark end states:

```php
'completed' => [], // Cannot transition from here
'cancelled' => [],
'deleted' => [],
```

### 3. Allow Corrections

Enable fixing mistakes:

```php
'rejected' => ['draft'], // Can resubmit
'failed' => ['pending'], // Can retry
```

### 4. Document Business Rules

```php
/**
 * Order State Machine:
 *
 * pending → processing (payment received)
 * processing → completed (items shipped)
 * processing → failed (payment declined)
 * failed → pending (retry payment)
 * Any → cancelled (customer request)
 */
'App\\Models\\Order' => [...],
```

## Next Steps

- [Advanced Usage](#doc-docs-advanced-usage) - Complex workflows and patterns
- [Configuration](#doc-docs-configuration) - All configuration options

<a id="doc-docs-advanced-usage"></a>

# Advanced Usage

This guide covers advanced patterns and techniques for working with states in complex scenarios.

## Complex Query Patterns

### Find Contexts by State

```php
use Cline\States\Database\Models\State;

// Find all users with 'active' state
$activeUserIds = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->withState('active')
    ->pluck('context_id');

$activeUsers = User::query()->whereIn('id', $activeUserIds)->get();
```

### Find Contexts with Multiple States

```php
// Users who are both active AND premium
$contextIds = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->withState(['active', 'premium'])
    ->select('context_id')
    ->groupBy('context_id')
    ->havingRaw('COUNT(DISTINCT name) = 2')
    ->pluck('context_id');

$premiumActiveUsers = User::query()->whereIn('id', $contextIds)->get();
```

### Complex State Queries

```php
// Users in specific workflow states but not banned
$users = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->where('namespace', 'workflow')
    ->whereIn('name', ['onboarding', 'active'])
    ->whereNotExists(function ($query) {
        $query->select('*')
            ->from('states as s2')
            ->whereColumn('s2.context_id', 'states.context_id')
            ->whereColumn('s2.context_type', 'states.context_type')
            ->where('s2.name', 'banned');
    })
    ->pluck('context_id');
```

## Bulk Operations

### Bulk State Assignment

```php
$users = User::where('created_at', '>', now()->subDay())->get();

foreach ($users as $user) {
    States::for($user)->assign('onboarding.welcome_sent');
}

// Or with DB transaction
DB::transaction(function () use ($users) {
    foreach ($users as $user) {
        States::for($user)
            ->by($admin)
            ->because('Bulk onboarding initialization')
            ->assign('onboarding.welcome_sent');
    }
});
```

### Bulk Transitions

```php
// Transition all pending users to active
$pendingUsers = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->withState('pending')
    ->pluck('context_id');

foreach (User::find($pendingUsers) as $user) {
    States::for($user)
        ->by($admin)
        ->because('Bulk activation campaign')
        ->transition('pending', 'active');
}
```

## State Scopes

### Eloquent Query Scopes

```php
class User extends Model
{
    use HasStates;

    public function scopeWithState(Builder $query, string $state): Builder
    {
        $stateContexts = State::query()
            ->where('context_type', $this->getMorphClass())
            ->withState($state)
            ->pluck('context_id');

        return $query->whereIn('id', $stateContexts);
    }

    public function scopeWithoutState(Builder $query, string $state): Builder
    {
        $stateContexts = State::query()
            ->where('context_type', $this->getMorphClass())
            ->withState($state)
            ->pluck('context_id');

        return $query->whereNotIn('id', $stateContexts);
    }
}

// Usage
$activeUsers = User::withState('active')->get();
$nonPremiumUsers = User::withoutState('premium')->get();
```

### Global Scopes

Automatically filter by state:

```php
class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $activeContexts = State::query()
            ->where('context_type', $model->getMorphClass())
            ->withState('active')
            ->pluck('context_id');

        $builder->whereIn($model->getKeyName(), $activeContexts);
    }
}

class User extends Model
{
    use HasStates;

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveScope());
    }
}

// Now all queries automatically filter to active users
$users = User::all(); // Only active
$allUsers = User::withoutGlobalScope(ActiveScope::class)->get();
```

## State Observers

### Model Observers

```php
class UserStateObserver
{
    public function created(User $user): void
    {
        // Assign initial state on creation
        States::for($user)->assign('pending');
    }

    public function deleting(User $user): void
    {
        // Transition to deleted before model deletion
        if (States::hasState($user, 'active')) {
            States::for($user)
                ->by(auth()->user())
                ->because('Model deleted')
                ->transition('active', 'deleted');
        }
    }
}

// Register observer
User::observe(UserStateObserver::class);
```

### Event Listeners

```php
use Cline\States\Events\StateTransitioned;

class NotifyOnActivation
{
    public function handle(StateTransitioned $event): void
    {
        if (!$event->context instanceof User) {
            return;
        }

        if ($event->transition->to_state === 'active') {
            Mail::to($event->context)->send(
                new WelcomeEmail($event->context)
            );
        }
    }
}

// In EventServiceProvider
protected $listen = [
    StateTransitioned::class => [
        NotifyOnActivation::class,
        LogStateChange::class,
        UpdateAnalytics::class,
    ],
];
```

## Conditional State Logic

### State-Based Authorization

```php
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function update(User $actor, User $user): Response
    {
        // Users can only update their own profile if active
        if ($actor->id === $user->id && States::hasState($user, 'active')) {
            return Response::allow();
        }

        // Admins can update any user
        if (States::hasState($actor, 'role.admin')) {
            return Response::allow();
        }

        return Response::deny('You cannot update this user.');
    }

    public function delete(User $actor, User $user): Response
    {
        // Cannot delete users in final states
        if (States::hasState($user, 'deleted')) {
            return Response::deny('User already deleted.');
        }

        return States::hasState($actor, 'role.admin')
            ? Response::allow()
            : Response::deny();
    }
}
```

### State-Based Routing

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        if (States::hasState($user, 'onboarding.incomplete')) {
            return redirect('/onboarding');
        }

        if (States::hasState($user, 'suspended')) {
            return redirect('/suspended');
        }

        return view('dashboard');
    });
});
```

### State-Based Middleware

```php
class RequireActiveState
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!States::hasState($user, 'active')) {
            abort(403, 'Your account is not active.');
        }

        return $next($request);
    }
}

// Usage
Route::middleware(['auth', RequireActiveState::class])
    ->group(function () {
        // Protected routes
    });
```

## State Aggregation

### Count Contexts by State

```php
$stateCounts = State::query()
    ->where('context_type', (new User())->getMorphClass())
    ->select('name', DB::raw('COUNT(*) as count'))
    ->groupBy('name')
    ->get();

/*
[
    ['name' => 'active', 'count' => 150],
    ['name' => 'pending', 'count' => 23],
    ['name' => 'suspended', 'count' => 5],
]
*/
```

### State Distribution

```php
function getStateDistribution(string $contextType): array
{
    return State::query()
        ->where('context_type', $contextType)
        ->select('name')
        ->selectRaw('COUNT(*) as count')
        ->selectRaw('COUNT(*) * 100.0 / (SELECT COUNT(*) FROM states WHERE context_type = ?) as percentage', [$contextType])
        ->groupBy('name')
        ->orderByDesc('count')
        ->get()
        ->toArray();
}
```

## State Caching

### Cache Current States

```php
use Illuminate\Support\Facades\Cache;

function getCachedStates(HasStateContext $context): Collection
{
    $cacheKey = "states.{$context->getStateContextType()}.{$context->getStateContextId()}";

    return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($context) {
        return States::getStates($context);
    });
}

// Invalidate on state change
States::for($user)
    ->assign('premium');

$cacheKey = "states.{$user->getMorphClass()}.{$user->getKey()}";
Cache::forget($cacheKey);
```

### Eager Loading States

```php
$users = User::with('states')->get();

foreach ($users as $user) {
    // States are already loaded, no N+1 queries
    $states = $user->states;
}
```

## Multi-State Workflows

### Parallel State Machines

```php
class Order extends Model
{
    use HasStates;

    public function getPaymentState(): ?string
    {
        return States::getStates($this, namespace: 'payment')
            ->first()?->name;
    }

    public function getFulfillmentState(): ?string
    {
        return States::getStates($this, namespace: 'fulfillment')
            ->first()?->name;
    }

    public function canShip(): bool
    {
        return $this->getPaymentState() === 'completed'
            && $this->getFulfillmentState() === 'ready';
    }
}

// Usage
States::for($order)->assign('payment.pending');
States::for($order)->assign('fulfillment.preparing');

States::for($order)->transition('payment.pending', 'payment.completed');
States::for($order)->transition('fulfillment.preparing', 'fulfillment.ready');

if ($order->canShip()) {
    States::for($order)->assign('fulfillment.shipped');
}
```

### State Composites

```php
class User extends Model
{
    use HasStates;

    public function getCompositeStatus(): string
    {
        $states = States::getStates($this);

        $isActive = $states->contains('name', 'active');
        $isPremium = $states->contains('name', 'premium');
        $isVerified = $states->contains('name', 'verified');

        return match (true) {
            $isActive && $isPremium && $isVerified => 'premium-verified',
            $isActive && $isPremium => 'premium',
            $isActive && $isVerified => 'verified',
            $isActive => 'active',
            default => 'inactive',
        };
    }
}
```

## Testing Patterns

### State Factories

```php
class UserFactory extends Factory
{
    public function active(): self
    {
        return $this->afterCreating(function (User $user) {
            States::for($user)->assign('active');
        });
    }

    public function premium(): self
    {
        return $this->afterCreating(function (User $user) {
            States::for($user)->assign('premium', [
                'tier' => 'gold',
                'expires_at' => now()->addYear(),
            ]);
        });
    }
}

// Usage in tests
$user = User::factory()->active()->premium()->create();
```

### State Assertions

```php
class StateAssertions
{
    public static function assertHasState(
        HasStateContext $context,
        string $state,
        ?HasStateContext $boundary = null,
        string $environment = 'default'
    ): void {
        PHPUnit::assertTrue(
            States::hasState($context, $state, $environment, $boundary),
            "Failed asserting that context has state '{$state}'"
        );
    }

    public static function assertHasStates(
        HasStateContext $context,
        array $states
    ): void {
        PHPUnit::assertTrue(
            States::hasAllStates($context, $states),
            "Failed asserting that context has all states: " . implode(', ', $states)
        );
    }
}

// Usage
StateAssertions::assertHasState($user, 'active');
StateAssertions::assertHasStates($user, ['active', 'verified']);
```

## Next Steps

- [Configuration](#doc-docs-configuration) - All configuration options
- Review the [test suite](../tests/) for more examples

<a id="doc-docs-configuration"></a>

# Configuration

This guide covers all configuration options available in the States package.

## Publishing Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="states-config"
```

This creates `config/states.php` in your application.

## Configuration Options

### State Machines

Define allowed state transitions for each context type:

```php
'machines' => [
    'App\\Models\\User' => [
        'transitions' => [
            'pending' => ['active', 'rejected'],
            'active' => ['suspended', 'deleted'],
            'suspended' => ['active', 'deleted'],
        ],
    ],

    'App\\Models\\Order' => [
        'transitions' => [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed', 'failed'],
            'failed' => ['pending'],
            'completed' => [],
            'cancelled' => [],
        ],
    ],
],
```

**How it works:**
- Key: Fully qualified context class name
- Value: Array with `transitions` key
- Each transition maps from_state => array of allowed to_states
- Empty array means terminal state (no transitions allowed)

**Example with namespaced states:**

```php
'App\\Models\\Document' => [
    'transitions' => [
        'workflow.draft' => ['workflow.review'],
        'workflow.review' => ['workflow.approved', 'workflow.rejected'],
        'workflow.approved' => ['workflow.published'],
    ],
],
```

See [State Machines](#doc-docs-state-machines) for detailed usage.

### Default Environment

Set the default environment for states:

```php
'default_environment' => env('STATES_DEFAULT_ENVIRONMENT', 'default'),
```

**Usage:**
```php
// Uses default environment
States::for($user)->assign('active');

// Equivalent to:
States::for($user, environment: config('states.default_environment'))
    ->assign('active');
```

**Environment variable:**
```env
STATES_DEFAULT_ENVIRONMENT=production
```

See [Environments](#doc-docs-environments) for detailed usage.

### Primary Key Type

Configure the primary key type for State and StateTransition models:

```php
'primary_key_type' => env('STATES_PRIMARY_KEY_TYPE', 'id'),
```

**Options:**
- `'id'` - Auto-incrementing integer (default)
- `'ulid'` - ULID (Universally Unique Lexicographically Sortable Identifier)
- `'uuid'` - UUID (Universally Unique Identifier)

**Environment variable:**
```env
STATES_PRIMARY_KEY_TYPE=ulid
```

**Migration compatibility:**

When using ULIDs or UUIDs, ensure your migrations use the appropriate column type:

```php
// For ULID
Schema::create('states', function (Blueprint $table) {
    $table->ulid('id')->primary();
    // ...
});

// For UUID
Schema::create('states', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // ...
});
```

### Morph Key Types

Configure the morph key types for polymorphic relationships:

```php
'context_morph_type' => env('STATES_CONTEXT_MORPH_TYPE', 'string'),
'boundary_morph_type' => env('STATES_BOUNDARY_MORPH_TYPE', 'string'),
'actor_morph_type' => env('STATES_ACTOR_MORPH_TYPE', 'string'),
```

**Options:**
- `'string'` - Class name stored as string (default, recommended)
- `'numeric'` - Integer type ID
- `'ulid'` - ULID type ID
- `'uuid'` - UUID type ID

**Environment variables:**
```env
STATES_CONTEXT_MORPH_TYPE=string
STATES_BOUNDARY_MORPH_TYPE=string
STATES_ACTOR_MORPH_TYPE=string
```

**When to use numeric:**
- When you need better performance on joins
- When your models use integer primary keys consistently

**When to use string (recommended):**
- More flexible, works with any primary key type
- Easier to debug (human-readable class names)
- Laravel default behavior

**Migration compatibility:**

```php
// For string (default)
$table->string('context_type');
$table->unsignedBigInteger('context_id');

// For numeric
$table->integer('context_type');
$table->unsignedBigInteger('context_id');

// For ULID
$table->string('context_type');
$table->ulid('context_id');

// For UUID
$table->string('context_type');
$table->uuid('context_id');
```

### Database Table Names

Customize the database table names:

```php
'tables' => [
    'states' => 'states',
    'state_transitions' => 'state_transitions',
],
```

**Custom table names:**

```php
'tables' => [
    'states' => 'application_states',
    'state_transitions' => 'application_state_history',
],
```

**Important:** If you customize table names, ensure you update your migrations before running them.

## Environment-Specific Configuration

### Development Environment

```php
// config/states.php
'default_environment' => env('STATES_DEFAULT_ENVIRONMENT', 'development'),
'machines' => [
    // More permissive transitions for testing
    'App\\Models\\User' => [
        'transitions' => [
            'pending' => ['active', 'rejected', 'suspended', 'deleted'],
            'active' => ['pending', 'suspended', 'deleted'],
            'suspended' => ['pending', 'active', 'deleted'],
        ],
    ],
],
```

### Production Environment

```php
// config/states.php
'default_environment' => env('STATES_DEFAULT_ENVIRONMENT', 'production'),
'machines' => [
    // Strict transitions
    'App\\Models\\User' => [
        'transitions' => [
            'pending' => ['active', 'rejected'],
            'active' => ['suspended', 'deleted'],
            'suspended' => ['deleted'],
        ],
    ],
],
```

### Testing Environment

```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();

    // Use test-specific environment
    config(['states.default_environment' => 'testing']);

    // Disable state machine validation for tests
    config(['states.machines' => []]);
}
```

## Runtime Configuration

Override configuration at runtime:

```php
// Change default environment
config(['states.default_environment' => 'staging']);

// Add state machine rules
config([
    'states.machines.App\\Models\\User.transitions.pending' => ['active'],
]);

// Remove state machine validation
config(['states.machines' => []]);
```

## Best Practices

### 1. Use Environment Variables

Store environment-specific values in `.env`:

```env
STATES_DEFAULT_ENVIRONMENT=production
STATES_PRIMARY_KEY_TYPE=ulid
STATES_CONTEXT_MORPH_TYPE=string
STATES_BOUNDARY_MORPH_TYPE=string
STATES_ACTOR_MORPH_TYPE=string
```

### 2. Document State Machines

Add comments to explain business logic:

```php
'machines' => [
    /**
     * User Account States:
     *
     * - pending: Email verification required
     * - active: Full account access
     * - suspended: Temporary restriction
     * - deleted: Permanent removal
     */
    'App\\Models\\User' => [
        'transitions' => [
            'pending' => ['active', 'rejected'],
            'active' => ['suspended', 'deleted'],
            'suspended' => ['active', 'deleted'],
        ],
    ],
],
```

### 3. Keep Machines Simple

Break complex workflows into namespaced states:

```php
// Good: Separate concerns
'App\\Models\\Order' => [
    'transitions' => [
        // Payment flow
        'payment.pending' => ['payment.completed', 'payment.failed'],
        'payment.failed' => ['payment.pending'],

        // Fulfillment flow (independent)
        'fulfillment.pending' => ['fulfillment.processing'],
        'fulfillment.processing' => ['fulfillment.shipped'],
    ],
],

// Bad: Complex single flow
'App\\Models\\Order' => [
    'transitions' => [
        'pending' => ['processing', 'payment_failed'],
        'processing' => ['shipping', 'fulfillment_failed'],
        'payment_failed' => ['pending', 'cancelled'],
        // ...many more states
    ],
],
```

### 4. Version Configuration

Track changes to state machines in version control:

```php
// config/states.php
/**
 * State machine configuration
 *
 * @version 1.2.0
 * @updated 2024-01-15
 *
 * Changelog:
 * - 1.2.0: Added 'rejected' to 'pending' transition for User
 * - 1.1.0: Added Order state machine
 * - 1.0.0: Initial User state machine
 */
return [
    // ...
];
```

## Migration Guide

### Changing Primary Key Type

**From `id` to `ulid`:**

1. Update configuration:
```php
'primary_key_type' => 'ulid',
```

2. Create migration:
```php
Schema::table('states', function (Blueprint $table) {
    $table->ulid('id')->change();
});

Schema::table('state_transitions', function (Blueprint $table) {
    $table->ulid('id')->change();
    $table->ulid('state_id')->change();
});
```

3. Migrate existing data if needed

### Changing Morph Type

**From `string` to `numeric`:**

1. Create morph map in `AppServiceProvider`:
```php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::enforceMorphMap([
        1 => \App\Models\User::class,
        2 => \App\Models\Team::class,
    ]);
}
```

2. Update configuration:
```php
'context_morph_type' => 'numeric',
```

3. Create migration:
```php
Schema::table('states', function (Blueprint $table) {
    $table->integer('context_type')->change();
    $table->integer('boundary_type')->nullable()->change();
});
```

4. Migrate existing data

## Next Steps

- [Basic Usage](#doc-docs-basic-usage) - Start using states
- [State Machines](#doc-docs-state-machines) - Configure workflow validation
- [Advanced Usage](#doc-docs-advanced-usage) - Complex patterns and techniques
