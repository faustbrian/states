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

- [Transition History](transition-history.md) - Track changes per environment
- [State Machines](state-machines.md) - Validate transitions per environment
- [Advanced Usage](advanced-usage.md) - Complex multi-environment patterns
