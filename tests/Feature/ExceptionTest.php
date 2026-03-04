<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Contracts\HasStateContext;
use Cline\States\Exceptions\ContextDoesNotImplementInterfaceException;
use Cline\States\Exceptions\ContextMissingIdentifierException;
use Cline\States\Exceptions\InvalidContextException;
use Cline\States\Exceptions\InvalidStateTransitionException;
use Cline\States\Exceptions\InvalidTransitionException;
use Cline\States\Exceptions\TransitionNotAllowedByConfigurationException;
use Tests\Fixtures\Transaction;

describe('ContextDoesNotImplementInterfaceException', function (): void {
    test('forObject creates exception with correct message', function (): void {
        $object = new stdClass();

        $exception = ContextDoesNotImplementInterfaceException::forObject($object);

        expect($exception)->toBeInstanceOf(InvalidContextException::class)
            ->and($exception->getMessage())->toContain('stdClass')
            ->and($exception->getMessage())->toContain('must implement')
            ->and($exception->getMessage())->toContain(HasStateContext::class);
    });
});

describe('ContextMissingIdentifierException', function (): void {
    test('forContext creates exception with correct message', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        $exception = ContextMissingIdentifierException::forContext($transaction);

        expect($exception)->toBeInstanceOf(InvalidContextException::class)
            ->and($exception->getMessage())->toContain(Transaction::class)
            ->and($exception->getMessage())->toContain('returned null or empty identifier')
            ->and($exception->getMessage())->toContain('getStateContextId()');
    });
});

describe('TransitionNotAllowedByConfigurationException', function (): void {
    test('fromTo creates exception with correct message', function (): void {
        $exception = TransitionNotAllowedByConfigurationException::fromTo('payment.pending', 'payment.cancelled');

        expect($exception)->toBeInstanceOf(InvalidTransitionException::class)
            ->and($exception->getMessage())->toBe("Transition from 'payment.pending' to 'payment.cancelled' is not allowed by state machine configuration.");
    });
});

describe('InvalidStateTransitionException', function (): void {
    test('forContext creates exception with correct message', function (): void {
        $exception = InvalidStateTransitionException::forContext('pending', 'paid', 'Transaction');

        expect($exception)->toBeInstanceOf(InvalidTransitionException::class)
            ->and($exception->getMessage())->toBe("Invalid state transition from 'pending' to 'paid' for context 'Transaction'.");
    });
});
