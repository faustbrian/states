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

- [State Machines](state-machines.md) - Validate transitions before recording
- [Advanced Usage](advanced-usage.md) - Complex transition patterns
- [Configuration](configuration.md) - Configure transition tracking
