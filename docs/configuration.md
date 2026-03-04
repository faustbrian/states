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

See [State Machines](state-machines.md) for detailed usage.

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

See [Environments](environments.md) for detailed usage.

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

- [Basic Usage](basic-usage.md) - Start using states
- [State Machines](state-machines.md) - Configure workflow validation
- [Advanced Usage](advanced-usage.md) - Complex patterns and techniques
