<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Database\Queries;

use Cline\States\Contracts\HasStateContext;
use Illuminate\Database\Eloquent\Builder;

use function explode;
use function str_contains;

/**
 * Custom query builder for state queries.
 *
 * Provides fluent methods for querying states by context, namespace,
 * environment, and boundary without polluting model classes.
 *
 * @author Brian Faust <brian@cline.sh>
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModel>
 */
final class StateQueryBuilder extends Builder
{
    /**
     * Filter states by context.
     */
    public function forContext(HasStateContext $context): static
    {
        return $this->where('context_type', $context->getStateContextType())
            ->where('context_id', $context->getStateContextId());
    }

    /**
     * Filter states by namespace.
     */
    public function inNamespace(?string $namespace): static
    {
        return $this->where('namespace', $namespace);
    }

    /**
     * Filter states by environment.
     */
    public function inEnvironment(string $environment = 'default'): static
    {
        return $this->where('environment', $environment);
    }

    /**
     * Filter states by boundary.
     */
    public function forBoundary(?HasStateContext $boundary): static
    {
        if (!$boundary instanceof HasStateContext) {
            return $this->whereNull('boundary_type')
                ->whereNull('boundary_id');
        }

        return $this->where('boundary_type', $boundary->getStateContextType())
            ->where('boundary_id', $boundary->getStateContextId());
    }

    /**
     * Filter by specific state name(s).
     *
     * @param array<int, string>|string $names
     */
    public function withStateName(string|array $names): static
    {
        return $this->whereIn('name', (array) $names);
    }

    /**
     * Filter by excluding specific state name(s).
     *
     * @param array<int, string>|string $names
     */
    public function withoutStateName(string|array $names): static
    {
        return $this->whereNotIn('name', (array) $names);
    }

    /**
     * Filter by fully qualified state name (namespace.name or name).
     *
     * @param array<int, string>|string $states
     */
    public function withState(string|array $states): static
    {
        $states = (array) $states;

        return $this->where(function ($query) use ($states): void {
            foreach ($states as $state) {
                $query->orWhere(function ($q) use ($state): void {
                    $stateStr = (string) $state;

                    if (str_contains($stateStr, '.')) {
                        [$namespace, $name] = explode('.', $stateStr, 2);
                        $q->where('namespace', $namespace)->where('name', $name);
                    } else {
                        $q->where('name', $stateStr)->whereNull('namespace');
                    }
                });
            }
        });
    }

    /**
     * Filter by excluding fully qualified state name.
     *
     * @param array<int, string>|string $states
     */
    public function withoutState(string|array $states): static
    {
        $states = (array) $states;

        foreach ($states as $state) {
            $this->where(function ($q) use ($state): void {
                $stateStr = (string) $state;

                if (str_contains($stateStr, '.')) {
                    [$namespace, $name] = explode('.', $stateStr, 2);
                    $q->whereNot(fn ($query) => $query->where('namespace', $namespace)
                        ->where('name', $name));
                } else {
                    $q->whereNot(fn ($query) => $query->where('name', $stateStr)
                        ->whereNull('namespace'));
                }
            });
        }

        return $this;
    }
}
