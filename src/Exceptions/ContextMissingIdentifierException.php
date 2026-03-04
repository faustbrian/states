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
 * Exception thrown when context identifier is null or empty.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ContextMissingIdentifierException extends InvalidContextException
{
    public static function forContext(HasStateContext $context): self
    {
        $class = $context::class;

        return new self(sprintf("Context '%s' returned null or empty identifier from getStateContextId().", $class));
    }
}
