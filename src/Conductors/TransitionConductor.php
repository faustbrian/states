<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Conductors;

use Cline\States\Contracts\HasStateContext;
use Cline\States\Database\Models\State;
use Cline\States\Database\Models\StateTransition;
use Cline\States\Events\StateTransitioned;
use Cline\States\Exceptions\TransitionNotAllowedByConfigurationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function collect;
use function config;
use function count;
use function explode;
use function in_array;
use function str_contains;

/**
 * Fluent API for state transitions.
 *
 * Provides methods to assign, transition, and remove states with
 * full validation and history tracking.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TransitionConductor
{
    private ?HasStateContext $actor = null;

    private ?string $reason = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?HasStateContext $boundary = null;

    private string $environment = 'default';

    private ?string $namespaceFilter = null;

    public function __construct(
        private readonly HasStateContext $context,
    ) {}

    /**
     * Set the actor who is performing this operation.
     */
    public function by(?HasStateContext $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    /**
     * Set the reason for this transition.
     */
    public function because(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Set metadata for this transition.
     *
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Scope operations to a specific boundary context.
     */
    public function within(?HasStateContext $boundary): self
    {
        $this->boundary = $boundary;

        return $this;
    }

    /**
     * Scope operations to a specific environment.
     */
    public function in(string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Filter query operations to a specific namespace.
     */
    public function namespace(string $namespace): self
    {
        $this->namespaceFilter = $namespace;

        return $this;
    }

    /**
     * Assign a new state to the context.
     *
     * Creates the state if it doesn't exist. Records transition history.
     *
     * @param null|array<string, mixed> $data
     */
    public function assign(string $stateName, ?array $data = null): State
    {
        [$namespace, $name] = $this->parseStateName($stateName);

        /** @var State */
        return DB::transaction(function () use ($stateName, $namespace, $name, $data): State {
            /** @var State $state */
            $state = State::query()->firstOrCreate(
                [
                    'context_type' => $this->context->getStateContextType(),
                    'context_id' => $this->context->getStateContextId(),
                    'namespace' => $namespace,
                    'name' => $name,
                    'environment' => $this->environment,
                    'boundary_type' => $this->boundary?->getStateContextType(),
                    'boundary_id' => $this->boundary?->getStateContextId(),
                ],
                [
                    'data' => $data,
                ],
            );

            // Record initial transition
            /** @var StateTransition $transition */
            $transition = $state->transitions()->create([
                'from_state' => null,
                'to_state' => $stateName,
                'actor_type' => $this->actor?->getStateContextType(),
                'actor_id' => $this->actor?->getStateContextId(),
                'reason' => $this->reason,
                'metadata' => $this->metadata,
            ]);

            Event::dispatch(
                new StateTransitioned(
                    context: $this->context,
                    state: $state,
                    transition: $transition,
                    actor: $this->actor,
                ),
            );

            return $state;
        });
    }

    /**
     * Transition from one state to another with validation.
     *
     * @param null|array<string, mixed> $data
     */
    public function transition(string $from, string $to, ?array $data = null): State
    {
        $this->validateTransition($from, $to);

        [$fromNamespace, $fromName] = $this->parseStateName($from);
        [$toNamespace, $toName] = $this->parseStateName($to);

        /** @var State */
        return DB::transaction(function () use ($fromNamespace, $fromName, $toNamespace, $toName, $from, $to, $data): State {
            // Get old state and its transitions
            /** @var null|State $oldState */
            $oldState = State::query()
                ->forContext($this->context)
                ->where('namespace', $fromNamespace)
                ->where('name', $fromName)
                ->inEnvironment($this->environment)
                ->forBoundary($this->boundary)
                ->first();

            // Create new state
            /** @var State $state */
            $state = State::query()->create([
                'context_type' => $this->context->getStateContextType(),
                'context_id' => $this->context->getStateContextId(),
                'namespace' => $toNamespace,
                'name' => $toName,
                'environment' => $this->environment,
                'boundary_type' => $this->boundary?->getStateContextType(),
                'boundary_id' => $this->boundary?->getStateContextId(),
                'data' => $data,
            ]);

            // Copy old transitions to new state
            if ($oldState) {
                /** @var Collection<int, StateTransition> $transitions */
                $transitions = $oldState->transitions;

                foreach ($transitions as $oldTransition) {
                    $state->transitions()->create([
                        'from_state' => $oldTransition->from_state,
                        'to_state' => $oldTransition->to_state,
                        'actor_type' => $oldTransition->actor_type,
                        'actor_id' => $oldTransition->actor_id,
                        'reason' => $oldTransition->reason,
                        'metadata' => $oldTransition->metadata,
                        'created_at' => $oldTransition->created_at,
                    ]);
                }

                // Delete old state
                $oldState->delete();
            }

            // Record new transition
            /** @var StateTransition $transition */
            $transition = $state->transitions()->create([
                'from_state' => $from,
                'to_state' => $to,
                'actor_type' => $this->actor?->getStateContextType(),
                'actor_id' => $this->actor?->getStateContextId(),
                'reason' => $this->reason,
                'metadata' => $this->metadata,
            ]);

            Event::dispatch(
                new StateTransitioned(
                    context: $this->context,
                    state: $state,
                    transition: $transition,
                    actor: $this->actor,
                ),
            );

            return $state;
        });
    }

    /**
     * Remove a state from the context.
     */
    public function remove(string $stateName): bool
    {
        [$namespace, $name] = $this->parseStateName($stateName);

        /** @var bool */
        return DB::transaction(function () use ($namespace, $name, $stateName): bool {
            /** @var null|State $state */
            $state = State::query()
                ->forContext($this->context)
                ->where('namespace', $namespace)
                ->where('name', $name)
                ->inEnvironment($this->environment)
                ->forBoundary($this->boundary)
                ->first();

            if (!$state) {
                return false;
            }

            // Record removal transition
            $state->transitions()->create([
                'from_state' => $stateName,
                'to_state' => '__removed__',
                'actor_type' => $this->actor?->getStateContextType(),
                'actor_id' => $this->actor?->getStateContextId(),
                'reason' => $this->reason ?? 'State removed',
                'metadata' => $this->metadata,
            ]);

            return (bool) $state->delete();
        });
    }

    /**
     * Get all states matching the current scope.
     *
     * @return Collection<int, State>
     */
    public function get(): Collection
    {
        return State::query()
            ->forContext($this->context)
            ->when($this->namespaceFilter !== null, fn ($q) => $q->inNamespace($this->namespaceFilter))
            ->inEnvironment($this->environment)
            ->forBoundary($this->boundary)
            ->get();
    }

    /**
     * Check if context has a specific state.
     */
    public function has(string $state): bool
    {
        return State::query()
            ->forContext($this->context)
            ->withState($state)
            ->inEnvironment($this->environment)
            ->forBoundary($this->boundary)
            ->exists();
    }

    /**
     * Check if context has any of the given states.
     *
     * @param array<int, string> $states
     */
    public function hasAny(array $states): bool
    {
        /** @var bool $result */
        $result = State::query()
            ->forContext($this->context)
            ->where(function ($q) use ($states): void {
                collect($states)->each(fn ($state) => $q->orWhere->withState($state));
            })
            ->inEnvironment($this->environment)
            ->forBoundary($this->boundary)
            ->exists();

        return $result;
    }

    /**
     * Check if context has all of the given states.
     *
     * @param array<int, string> $states
     */
    public function hasAll(array $states): bool
    {
        /** @var int $count */
        $count = State::query()
            ->forContext($this->context)
            ->where(function ($q) use ($states): void {
                collect($states)->each(fn ($state) => $q->orWhere->withState($state));
            })
            ->inEnvironment($this->environment)
            ->forBoundary($this->boundary)
            ->count();

        return $count === count($states);
    }

    /**
     * Parse state name into namespace and name components.
     *
     * @return array{0: null|string, 1: string}
     */
    private function parseStateName(string $stateName): array
    {
        if (str_contains($stateName, '.')) {
            [$namespace, $name] = explode('.', $stateName, 2);

            return [$namespace, $name];
        }

        return [null, $stateName];
    }

    /**
     * Validate state transition against configured state machine.
     */
    private function validateTransition(string $from, string $to): void
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = config('states.machines', []);
        $contextType = $this->context->getStateContextType();

        if (!isset($config[$contextType])) {
            return; // No validation configured
        }

        /** @var array<string, array<int, string>> $contextConfig */
        $contextConfig = $config[$contextType];

        /** @var array<string, array<int, string>> $transitions */
        $transitions = $contextConfig['transitions'] ?? [];

        if (empty($transitions)) {
            return; // No restrictions
        }

        /** @var array<int, string> $allowedTransitions */
        $allowedTransitions = $transitions[$from] ?? [];

        if (!in_array($to, $allowedTransitions, true)) {
            throw TransitionNotAllowedByConfigurationException::fromTo($from, $to);
        }
    }
}
