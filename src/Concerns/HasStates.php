<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Concerns;

use Cline\States\Database\Models\State;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

use function config;

/**
 * Trait for Eloquent models to implement HasStateContext interface.
 *
 * Provides default implementation of HasStateContext contract
 * and the states relationship. Use States::for($model) facade
 * for all state management operations.
 *
 * @mixin Model
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasStates
{
    /**
     * Get the state context ID.
     */
    public function getStateContextId(): string|int
    {
        /** @var int|string */
        return $this->getKey();
    }

    /**
     * Get the state context type.
     */
    public function getStateContextType(): string
    {
        return $this->getMorphClass();
    }

    /**
     * Get all states for this model.
     *
     * @phpstan-return MorphMany<State, $this>
     */
    public function states(): MorphMany
    {
        /** @var class-string<State> $stateClass */
        $stateClass = config('states.models.state', State::class);

        return $this->morphMany($stateClass, 'context');
    }
}
