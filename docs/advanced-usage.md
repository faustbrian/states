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

- [Configuration](configuration.md) - All configuration options
- Review the [test suite](../tests/) for more examples
