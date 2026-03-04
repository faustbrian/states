<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Database\Models;

use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * State transition history model.
 *
 * Records each state transition with full audit trail including
 * the actor who triggered it and contextual metadata.
 *
 * @property null|string               $actor_id
 * @property null|string               $actor_type
 * @property Carbon                    $created_at
 * @property null|string               $from_state
 * @property int                       $id
 * @property null|array<string, mixed> $metadata
 * @property null|string               $reason
 * @property int                       $state_id
 * @property string                    $to_state
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StateTransition extends Model
{
    use HasFactory;
    use HasVariablePrimaryKey;

    public const null UPDATED_AT = null;

    #[Override()]
    protected $fillable = [
        'state_id',
        'from_state',
        'to_state',
        'actor_type',
        'actor_id',
        'reason',
        'metadata',
    ];

    #[Override()]
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the state this transition belongs to.
     *
     * @phpstan-return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Get the actor who triggered this transition.
     *
     * @phpstan-return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id');
    }
}
