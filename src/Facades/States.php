<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Facades;

use Cline\States\Conductors\TransitionConductor;
use Cline\States\Contracts\HasStateContext;
use Cline\States\Database\Models\State;
use Cline\States\Database\Queries\StateQueryBuilder;
use Cline\States\StateManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the States package.
 *
 * Fluent API for state management:
 * - States::for($context)->assign('state.name')
 * - States::for($context)->within($boundary)->in('prod')->assign('state.name')
 * - States::for($context)->namespace('role')->get()
 * - States::for($context)->has('state.name')
 *
 * @method static TransitionConductor      for(HasStateContext $context)
 * @method static Collection<int, State>   getStates(HasStateContext $context, ?string $namespace = null, string $environment = 'default', ?HasStateContext $boundary = null)
 * @method static bool                     hasAllStates(HasStateContext $context, array<int, string> $states, string $environment = 'default', ?HasStateContext $boundary = null)
 * @method static bool                     hasAnyState(HasStateContext $context, array<int, string> $states, string $environment = 'default', ?HasStateContext $boundary = null)
 * @method static bool                     hasState(HasStateContext $context, string $state, string $environment = 'default', ?HasStateContext $boundary = null)
 * @method static StateQueryBuilder<State> query()
 *
 * @author Brian Faust <brian@cline.sh>
 * @see StateManager
 * @see TransitionConductor
 */
final class States extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StateManager::class;
    }
}
