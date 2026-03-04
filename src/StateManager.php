<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States;

use Cline\States\Conductors\TransitionConductor;
use Cline\States\Contracts\HasStateContext;
use Cline\States\Database\Models\State;
use Cline\States\Database\Queries\StateQueryBuilder;
use Deprecated;
use Illuminate\Database\Eloquent\Collection;

/**
 * Main state manager providing fluent API access.
 *
 * Entry point for all state operations. Use this to create conductors
 * for managing states on contexts.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StateManager
{
    /**
     * Create a transition conductor for the given context.
     *
     * Returns a fluent interface for managing states on the context.
     * Chain with ->within() for boundaries and ->in() for environments.
     */
    public function for(HasStateContext $context): TransitionConductor
    {
        return new TransitionConductor(context: $context);
    }

    /**
     * Create a query builder for states.
     *
     * @phpstan-return StateQueryBuilder<State>
     */
    public function query(): StateQueryBuilder
    {
        return State::query();
    }

    /**
     * Get all states for a context.
     *
     * @return Collection<int, State>
     */
    #[Deprecated(message: 'Use States::for($context)->namespace($namespace)->in($environment)->within($boundary)->get() instead')]
    public function getStates(
        HasStateContext $context,
        ?string $namespace = null,
        string $environment = 'default',
        ?HasStateContext $boundary = null,
    ): Collection {
        $conductor = $this->for($context);

        if ($namespace !== null) {
            $conductor = $conductor->namespace($namespace);
        }

        if ($environment !== 'default') {
            $conductor = $conductor->in($environment);
        }

        if ($boundary instanceof HasStateContext) {
            $conductor = $conductor->within($boundary);
        }

        return $conductor->get();
    }

    /**
     * Check if context has a specific state.
     */
    #[Deprecated(message: 'Use States::for($context)->in($environment)->within($boundary)->has($state) instead')]
    public function hasState(
        HasStateContext $context,
        string $state,
        string $environment = 'default',
        ?HasStateContext $boundary = null,
    ): bool {
        $conductor = $this->for($context);

        if ($environment !== 'default') {
            $conductor = $conductor->in($environment);
        }

        if ($boundary instanceof HasStateContext) {
            $conductor = $conductor->within($boundary);
        }

        return $conductor->has($state);
    }

    /**
     * Check if context has any of the given states.
     *
     * @param array<int, string> $states
     */
    #[Deprecated(message: 'Use States::for($context)->in($environment)->within($boundary)->hasAny($states) instead')]
    public function hasAnyState(
        HasStateContext $context,
        array $states,
        string $environment = 'default',
        ?HasStateContext $boundary = null,
    ): bool {
        $conductor = $this->for($context);

        if ($environment !== 'default') {
            $conductor = $conductor->in($environment);
        }

        if ($boundary instanceof HasStateContext) {
            $conductor = $conductor->within($boundary);
        }

        return $conductor->hasAny($states);
    }

    /**
     * Check if context has all of the given states.
     *
     * @param array<int, string> $states
     */
    #[Deprecated(message: 'Use States::for($context)->in($environment)->within($boundary)->hasAll($states) instead')]
    public function hasAllStates(
        HasStateContext $context,
        array $states,
        string $environment = 'default',
        ?HasStateContext $boundary = null,
    ): bool {
        $conductor = $this->for($context);

        if ($environment !== 'default') {
            $conductor = $conductor->in($environment);
        }

        if ($boundary instanceof HasStateContext) {
            $conductor = $conductor->within($boundary);
        }

        return $conductor->hasAll($states);
    }
}
