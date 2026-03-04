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

- [Advanced Usage](advanced-usage.md) - Complex workflows and patterns
- [Configuration](configuration.md) - All configuration options
