<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Conductors\TransitionConductor;
use Cline\States\Database\Queries\StateQueryBuilder;
use Cline\States\Facades\States;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

describe('StateManager::for()', function (): void {
    test('returns TransitionConductor instance', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        $conductor = States::for($transaction);

        expect($conductor)->toBeInstanceOf(TransitionConductor::class);
    });

    test('accepts boundary parameter', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->assign('payment.paid');

        expect(States::for($transaction)->within($team)->has('payment.paid'))->toBeTrue();
    });

    test('accepts environment parameter', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('test')->assign('payment.paid');

        expect(States::for($transaction)->in('test')->has('payment.paid'))->toBeTrue();
    });
});

describe('StateManager::query()', function (): void {
    test('returns StateQueryBuilder instance', function (): void {
        $query = States::query();

        expect($query)->toBeInstanceOf(StateQueryBuilder::class);
    });
});

describe('StateManager::getStates()', function (): void {
    test('returns all states for context', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('dispute.open');

        $states = States::getStates($transaction);

        expect($states)->toHaveCount(2);
    });

    test('filters by environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('production')->assign('payment.paid');
        States::for($transaction)->in('test')->assign('payment.pending');

        $states = States::getStates($transaction, environment: 'production');

        expect($states)->toHaveCount(1)
            ->and($states->first()->environment)->toBe('production');
    });

    test('filters by boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->within($team)->assign('payment.pending');

        $states = States::getStates($transaction, boundary: $team);

        expect($states)->toHaveCount(1)
            ->and($states->first()->boundary_id)->toEqual($team->id);
    });

    test('filters by namespace', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('dispute.open');
        States::for($transaction)->assign('refund.pending');

        $states = States::getStates($transaction, namespace: 'payment');

        expect($states)->toHaveCount(1)
            ->and($states->first()->namespace)->toBe('payment');
    });
});

describe('StateManager::hasState()', function (): void {
    test('returns true when state exists', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        expect(States::hasState($transaction, 'payment.paid'))->toBeTrue();
    });

    test('returns false when state does not exist', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        expect(States::hasState($transaction, 'payment.paid'))->toBeFalse();
    });

    test('respects environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('production')->assign('payment.paid');

        expect(States::hasState($transaction, 'payment.paid', environment: 'production'))->toBeTrue()
            ->and(States::hasState($transaction, 'payment.paid', environment: 'test'))->toBeFalse();
    });

    test('respects boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->assign('payment.paid');

        expect(States::hasState($transaction, 'payment.paid', boundary: $team))->toBeTrue()
            ->and(States::hasState($transaction, 'payment.paid', boundary: null))->toBeFalse();
    });
});

describe('StateManager::hasAnyState()', function (): void {
    test('returns true when any state exists', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        expect(States::hasAnyState($transaction, ['payment.paid', 'payment.pending']))->toBeTrue();
    });

    test('returns false when no states exist', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        expect(States::hasAnyState($transaction, ['payment.paid', 'payment.pending']))->toBeFalse();
    });

    test('respects environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('production')->assign('payment.paid');

        expect(States::hasAnyState($transaction, ['payment.paid', 'payment.pending'], environment: 'production'))->toBeTrue()
            ->and(States::hasAnyState($transaction, ['payment.paid', 'payment.pending'], environment: 'test'))->toBeFalse();
    });

    test('respects boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->assign('payment.paid');

        expect(States::hasAnyState($transaction, ['payment.paid', 'payment.pending'], boundary: $team))->toBeTrue()
            ->and(States::hasAnyState($transaction, ['payment.paid', 'payment.pending'], boundary: null))->toBeFalse();
    });
});

describe('StateManager::hasAllStates()', function (): void {
    test('returns true when all states exist', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('dispute.open');

        expect(States::hasAllStates($transaction, ['payment.paid', 'dispute.open']))->toBeTrue();
    });

    test('returns false when some states are missing', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        expect(States::hasAllStates($transaction, ['payment.paid', 'dispute.open']))->toBeFalse();
    });

    test('returns false when no states exist', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        expect(States::hasAllStates($transaction, ['payment.paid', 'dispute.open']))->toBeFalse();
    });

    test('respects environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('production')->assign('payment.paid');
        States::for($transaction)->in('production')->assign('dispute.open');
        States::for($transaction)->in('test')->assign('payment.paid');

        expect(States::hasAllStates($transaction, ['payment.paid', 'dispute.open'], environment: 'production'))->toBeTrue()
            ->and(States::hasAllStates($transaction, ['payment.paid', 'dispute.open'], environment: 'test'))->toBeFalse();
    });

    test('respects boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->assign('payment.paid');
        States::for($transaction)->within($team)->assign('dispute.open');

        expect(States::hasAllStates($transaction, ['payment.paid', 'dispute.open'], boundary: $team))->toBeTrue()
            ->and(States::hasAllStates($transaction, ['payment.paid', 'dispute.open'], boundary: null))->toBeFalse();
    });
});
