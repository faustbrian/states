<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Database\Models\State;
use Cline\States\Facades\States;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

test('can assign state to context', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.paid');

    expect(States::for($transaction)->has('payment.paid'))->toBeTrue();
});

test('can assign multiple states to same context', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.paid');
    States::for($transaction)->assign('dispute.reclamated');

    expect(States::for($transaction)->has('payment.paid'))->toBeTrue()
        ->and(States::for($transaction)->has('dispute.reclamated'))->toBeTrue();
});

test('can transition between states', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.pending');
    States::for($transaction)->transition('payment.pending', 'payment.paid');

    expect(States::for($transaction)->has('payment.pending'))->toBeFalse()
        ->and(States::for($transaction)->has('payment.paid'))->toBeTrue();
});

test('can isolate states by environment', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->in('production')->assign('payment.paid');
    States::for($transaction)->in('test')->assign('payment.pending');

    expect(States::for($transaction)->in('production')->has('payment.paid'))->toBeTrue()
        ->and(States::for($transaction)->in('test')->has('payment.pending'))->toBeTrue()
        ->and(States::for($transaction)->in('test')->has('payment.paid'))->toBeFalse();
});

test('can scope states by boundary', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
    $team1 = Team::query()->create(['name' => 'Team 1']);
    $team2 = Team::query()->create(['name' => 'Team 2']);

    States::for($transaction)->within($team1)->assign('payment.paid');
    States::for($transaction)->within($team2)->assign('payment.pending');

    expect(States::for($transaction)->within($team1)->has('payment.paid'))->toBeTrue()
        ->and(States::for($transaction)->within($team2)->has('payment.pending'))->toBeTrue()
        ->and(States::for($transaction)->within($team2)->has('payment.paid'))->toBeFalse();
});

test('records transition history', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.pending');
    States::for($transaction)->transition('payment.pending', 'payment.paid');

    $state = State::query()->forContext($transaction)->withState('payment.paid')->first();
    $transitions = $state->transitions;

    expect($transitions)->toHaveCount(2);

    // Verify initial assignment transition exists
    $initialTransition = $transitions->firstWhere(fn ($t): bool => $t->from_state === null && $t->to_state === 'payment.pending');
    expect($initialTransition)->not->toBeNull();

    // Verify state transition exists
    $stateTransition = $transitions->firstWhere(fn ($t): bool => $t->from_state === 'payment.pending' && $t->to_state === 'payment.paid');
    expect($stateTransition)->not->toBeNull();
});

test('can remove state', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.paid');

    expect(States::for($transaction)->has('payment.paid'))->toBeTrue();

    States::for($transaction)->remove('payment.paid');

    expect(States::for($transaction)->has('payment.paid'))->toBeFalse();
});

test('can check multiple states', function (): void {
    $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

    States::for($transaction)->assign('payment.paid');
    States::for($transaction)->assign('dispute.reclamated');

    expect(States::for($transaction)->hasAny(['payment.paid', 'payment.pending']))->toBeTrue()
        ->and(States::for($transaction)->hasAll(['payment.paid', 'dispute.reclamated']))->toBeTrue()
        ->and(States::for($transaction)->hasAll(['payment.paid', 'payment.refunded']))->toBeFalse();
});
