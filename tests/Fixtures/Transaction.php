<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\States\Concerns\HasStates;
use Cline\States\Contracts\HasStateContext;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Transaction extends Model implements HasStateContext
{
    use HasFactory;
    use HasStates;
    use HasVariablePrimaryKey;

    #[Override()]
    protected $guarded = [];
}
