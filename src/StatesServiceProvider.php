<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States;

use Cline\States\Database\Models\State;
use Cline\States\Database\Models\StateTransition;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function config;

/**
 * Service provider for the States package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('states')
            ->hasConfigFile()
            ->hasMigration('create_states_tables');
    }

    #[Override()]
    public function registeringPackage(): void
    {
        $this->app->singleton(StateManager::class, fn (): StateManager => new StateManager());
    }

    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerVariableKeys();

        // Configure custom table names if defined
        /** @var string $statesTable */
        $statesTable = config('states.tables.states', 'states');

        /** @var string $transitionsTable */
        $transitionsTable = config('states.tables.state_transitions', 'state_transitions');

        State::query()->getModel()->setTable($statesTable);
        StateTransition::query()->getModel()->setTable($transitionsTable);
    }

    /**
     * Register States models with VariableKeys for primary key configuration.
     *
     * Maps States models (State, StateTransition) to use the configured primary
     * key type from states.primary_key_type. This enables the use of auto-increment
     * IDs, ULIDs, or UUIDs as primary keys across all States models.
     */
    private function registerVariableKeys(): void
    {
        /** @var int|string $configValue */
        $configValue = config('states.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::ID;

        VariableKeys::map([
            State::class => [
                'primary_key_type' => $primaryKeyType,
            ],
            StateTransition::class => [
                'primary_key_type' => $primaryKeyType,
            ],
        ]);
    }
}
