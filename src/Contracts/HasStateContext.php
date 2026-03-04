<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Contracts;

/**
 * Interface for objects that can have states.
 *
 * Implement this interface on any class (models, DTOs, value objects)
 * that needs state management. The state system uses these methods to
 * identify and track states without coupling to Eloquent models.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface HasStateContext
{
    /**
     * Get the unique identifier for this context.
     *
     * For Eloquent models, this is typically the primary key.
     * For other objects, return any unique identifier.
     */
    public function getStateContextId(): string|int;

    /**
     * Get the type identifier for this context.
     *
     * For Eloquent models, use getMorphClass() to ensure morph map compatibility.
     * For other objects, return a unique string identifier (e.g., class name).
     */
    public function getStateContextType(): string;
}
