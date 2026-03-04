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
 * Exception thrown when transition is not allowed by state machine configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TransitionNotAllowedByConfigurationException extends InvalidTransitionException
{
    public static function fromTo(string $from, string $to): self
    {
        return new self(sprintf("Transition from '%s' to '%s' is not allowed by state machine configuration.", $from, $to));
    }
}
