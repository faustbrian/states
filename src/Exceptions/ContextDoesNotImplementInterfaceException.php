<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Exceptions;

use Cline\States\Contracts\HasStateContext;

use function sprintf;

/**
 * Exception thrown when context object does not implement required interface.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ContextDoesNotImplementInterfaceException extends InvalidContextException
{
    public static function forObject(object $object): self
    {
        $class = $object::class;

        return new self(sprintf("Context object '%s' must implement ", $class).HasStateContext::class);
    }
}
