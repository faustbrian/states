<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Exceptions;

use function sprintf;

/**
 * Exception thrown when invalid state transition is attempted for context.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStateTransitionException extends InvalidTransitionException
{
    public static function forContext(string $from, string $to, string $context): self
    {
        return new self(sprintf("Invalid state transition from '%s' to '%s' for context '%s'.", $from, $to, $context));
    }
}
