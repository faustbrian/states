<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\States\Exceptions;

use Throwable;

/**
 * Marker interface for all States package exceptions.
 *
 * Consumers can catch this interface to handle any exception
 * thrown by the States package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface StatesException extends Throwable {}
