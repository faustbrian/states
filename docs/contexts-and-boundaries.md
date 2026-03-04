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

- [Environments](environments.md) - Separate states across environments
- [Transition History](transition-history.md) - Track changes with full context
- [Advanced Usage](advanced-usage.md) - Complex patterns and queries
