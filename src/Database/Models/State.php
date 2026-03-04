<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Database\Models;

use Cline\States\Database\Queries\StateQueryBuilder;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Override;

use function sprintf;

/**
 * State model.
 *
 * Represents a single state assignment to a context (model/object) within
 * an optional namespace, environment, and boundary.
 *
 * @property null|string               $boundary_id
 * @property null|string               $boundary_type
 * @property string                    $context_id
 * @property string                    $context_type
 * @property Carbon                    $created_at
 * @property null|array<string, mixed> $data
 * @property string                    $environment
 * @property int                       $id
 * @property string                    $name
 * @property null|string               $namespace
 * @property Carbon                    $updated_at
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class State extends Model
{
    use HasFactory;
    use HasVariablePrimaryKey;

    #[Override()]
    protected $fillable = [
        'context_type',
        'context_id',
        'namespace',
        'name',
        'environment',
        'boundary_type',
        'boundary_id',
        'data',
    ];

    #[Override()]
    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the context that owns this state.
     *
     * @phpstan-return MorphTo<Model, $this>
     */
    public function context(): MorphTo
    {
        return $this->morphTo('context', 'context_type', 'context_id');
    }

    /**
     * Get the boundary that scopes this state.
     *
     * @phpstan-return MorphTo<Model, $this>
     */
    public function boundary(): MorphTo
    {
        return $this->morphTo('boundary', 'boundary_type', 'boundary_id');
    }

    /**
     * Get all transitions for this state.
     *
     * @phpstan-return HasMany<StateTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(StateTransition::class);
    }

    /**
     * Get the fully qualified state name.
     *
     * Returns 'namespace.name' if namespace exists, otherwise just 'name'.
     */
    public function getFullyQualifiedName(): string
    {
        return $this->namespace ? sprintf('%s.%s', $this->namespace, $this->name) : $this->name;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param Builder $query
     */
    #[Override()]
    public function newEloquentBuilder($query): StateQueryBuilder
    {
        return new StateQueryBuilder($query);
    }
}
