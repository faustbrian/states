<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Database\Models\State;
use Cline\States\Database\Models\StateTransition;
use Cline\States\Facades\States;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

describe('State model relationships', function (): void {
    test('belongs to context via morphTo', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();

        expect($state->context)->toBeInstanceOf(Transaction::class)
            ->and($state->context->id)->toBe($transaction->id);
    });

    test('belongs to boundary via morphTo', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();

        expect($state->boundary)->toBeInstanceOf(Team::class)
            ->and($state->boundary->id)->toBe($team->id);
    });

    test('has many transitions', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->transitions)->toHaveCount(2)
            ->and($state->transitions->first())->toBeInstanceOf(StateTransition::class);
    });
});

describe('State::getFullyQualifiedName()', function (): void {
    test('returns namespace.name when namespaced', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();

        expect($state->getFullyQualifiedName())->toBe('payment.paid');
    });

    test('returns name when not namespaced', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('active');

        $state = State::query()->forContext($transaction)->first();

        expect($state->getFullyQualifiedName())->toBe('active');
    });
});

describe('StateTransition model relationships', function (): void {
    test('belongs to state', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();
        $transition = $state->transitions()->first();

        expect($transition->state)->toBeInstanceOf(State::class)
            ->and($transition->state->id)->toBe($state->id);
    });

    test('belongs to actor via morphTo', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Admin']);

        States::for($transaction)->by($team)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();
        $transition = $state->transitions()->first();

        expect($transition->actor)->toBeInstanceOf(Team::class)
            ->and($transition->actor->id)->toBe($team->id);
    });
});

describe('HasStates trait', function (): void {
    test('getStateContextId returns model key', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        expect($transaction->getStateContextId())->toBe($transaction->id);
    });

    test('getStateContextType returns morph class', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        expect($transaction->getStateContextType())->toBe($transaction->getMorphClass());
    });

    test('states relationship returns morphMany', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('dispute.open');

        expect($transaction->states)->toHaveCount(2)
            ->and($transaction->states->first())->toBeInstanceOf(State::class);
    });
});
