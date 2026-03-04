<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | State Machines
    |--------------------------------------------------------------------------
    |
    | Define state transition rules for each context type. The key is the
    | context type (morph class), and the value defines allowed transitions
    | and optional environment enum classes.
    |
    | Example configuration:
    |
    | 'App\Models\Transaction' => [
    |     'environments' => App\Enums\TransactionEnvironment::class,
    |     'transitions' => [
    |         'payment.pending' => ['payment.paid', 'payment.failed'],
    |         'payment.paid' => ['payment.refunded'],
    |         'dispute.pending' => ['dispute.resolved', 'dispute.escalated'],
    |     ],
    | ],
    |
    */
    'machines' => [
        // Define your state machines here
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Environment
    |--------------------------------------------------------------------------
    |
    | The default environment to use when none is specified.
    |
    */
    'default_environment' => env('STATES_DEFAULT_ENVIRONMENT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The primary key type to use for State and StateTransition models.
    | Options: 'id' (auto-increment), 'ulid', 'uuid'
    |
    */
    'primary_key_type' => env('STATES_PRIMARY_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Morph Key Types
    |--------------------------------------------------------------------------
    |
    | The morph key types to use for polymorphic relationships.
    | Options: 'string', 'numeric', 'ulid', 'uuid'
    |
    */
    'context_morph_type' => env('STATES_CONTEXT_MORPH_TYPE', 'string'),
    'boundary_morph_type' => env('STATES_BOUNDARY_MORPH_TYPE', 'string'),
    'actor_morph_type' => env('STATES_ACTOR_MORPH_TYPE', 'string'),

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names used by the package.
    |
    */
    'tables' => [
        'states' => 'states',
        'state_transitions' => 'state_transitions',
    ],
];
