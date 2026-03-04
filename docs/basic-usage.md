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

- [Contexts and Boundaries](contexts-and-boundaries.md) - Scope states to specific contexts
- [Environments](environments.md) - Separate states across environments
- [Transition History](transition-history.md) - Track and analyze state changes
- [State Machines](state-machines.md) - Validate allowed transitions
