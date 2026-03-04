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

describe('StateQueryBuilder::forContext()', function (): void {
    test('filters states by context', function (): void {
        $transaction1 = Transaction::query()->create(['reference' => 'TXN-001']);
        $transaction2 = Transaction::query()->create(['reference' => 'TXN-002']);

        States::for($transaction1)->assign('payment.paid');
        States::for($transaction2)->assign('payment.pending');

        $states = State::query()->forContext($transaction1)->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->context_id)->toEqual($transaction1->id);
    });

    test('works with different model types', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->assign('active');
        States::for($team)->assign('verified');

        $transactionStates = State::query()->forContext($transaction)->get();
        $teamStates = State::query()->forContext($team)->get();

        expect($transactionStates)->toHaveCount(1)
            ->and($teamStates)->toHaveCount(1)
            ->and($transactionStates->first()->name)->toBe('active')
            ->and($teamStates->first()->name)->toBe('verified');
    });
});

describe('StateQueryBuilder::inNamespace()', function (): void {
    test('filters by namespace', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('dispute.pending');
        States::for($transaction)->assign('active');

        $paymentStates = State::query()
            ->forContext($transaction)
            ->inNamespace('payment')
            ->get();

        expect($paymentStates)->toHaveCount(1)
            ->and($paymentStates->first()->namespace)->toBe('payment');
    });

    test('filters null namespace', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('active');

        $nonNamespaced = State::query()
            ->forContext($transaction)
            ->inNamespace(null)
            ->get();

        expect($nonNamespaced)->toHaveCount(1)
            ->and($nonNamespaced->first()->name)->toBe('active');
    });
});

describe('StateQueryBuilder::inEnvironment()', function (): void {
    test('filters by environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->in('production')->assign('payment.paid');
        States::for($transaction)->in('test')->assign('payment.pending');

        $prodStates = State::query()
            ->forContext($transaction)
            ->inEnvironment('production')
            ->get();

        expect($prodStates)->toHaveCount(1)
            ->and($prodStates->first()->environment)->toBe('production');
    });

    test('defaults to "default" environment', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $states = State::query()
            ->forContext($transaction)
            ->inEnvironment()
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->environment)->toBe('default');
    });
});

describe('StateQueryBuilder::forBoundary()', function (): void {
    test('filters by boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team1 = Team::query()->create(['name' => 'Team 1']);
        $team2 = Team::query()->create(['name' => 'Team 2']);

        States::for($transaction)->within($team1)->assign('payment.paid');
        States::for($transaction)->within($team2)->assign('payment.pending');

        $team1States = State::query()
            ->forContext($transaction)
            ->forBoundary($team1)
            ->get();

        expect($team1States)->toHaveCount(1)
            ->and($team1States->first()->boundary_id)->toEqual($team1->id);
    });

    test('filters null boundary', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->within($team)->assign('payment.pending');

        $noBoundary = State::query()
            ->forContext($transaction)
            ->forBoundary(null)
            ->get();

        expect($noBoundary)->toHaveCount(1)
            ->and($noBoundary->first()->boundary_id)->toBeNull();
    });
});

describe('StateQueryBuilder::withState()', function (): void {
    test('filters by single state name', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');

        $states = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->name)->toBe('paid');
    });

    test('filters by multiple state names', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->assign('dispute.open');

        $states = State::query()
            ->forContext($transaction)
            ->withState(['payment.paid', 'payment.pending'])
            ->get();

        expect($states)->toHaveCount(2);
    });

    test('handles non-namespaced state names', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('active');
        States::for($transaction)->assign('payment.paid');

        $states = State::query()
            ->forContext($transaction)
            ->withState('active')
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->name)->toBe('active')
            ->and($states->first()->namespace)->toBeNull();
    });
});

describe('StateQueryBuilder::withStateName()', function (): void {
    test('filters by single state name', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');

        $states = State::query()
            ->forContext($transaction)
            ->withStateName('paid')
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->name)->toBe('paid');
    });

    test('filters by multiple state names', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->assign('dispute.open');

        $states = State::query()
            ->forContext($transaction)
            ->withStateName(['paid', 'pending'])
            ->get();

        expect($states)->toHaveCount(2);
    });
});

describe('StateQueryBuilder::withoutStateName()', function (): void {
    test('excludes specific state name', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->assign('dispute.open');

        $states = State::query()
            ->forContext($transaction)
            ->withoutStateName('paid')
            ->get();

        expect($states)->toHaveCount(2)
            ->and($states->pluck('name')->toArray())->not->toContain('paid');
    });
});

describe('StateQueryBuilder::withoutState()', function (): void {
    test('excludes fully qualified state', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->assign('dispute.open');

        $states = State::query()
            ->forContext($transaction)
            ->withoutState('payment.paid')
            ->get();

        expect($states)->toHaveCount(2);
    });

    test('excludes non-namespaced state', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('active');
        States::for($transaction)->assign('payment.paid');

        $states = State::query()
            ->forContext($transaction)
            ->withoutState('active')
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->name)->toBe('paid');
    });
});

describe('StateQueryBuilder method chaining', function (): void {
    test('chains all query methods', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Team 1']);

        States::for($transaction)->within($team)->in('production')->assign('payment.paid');
        States::for($transaction)->in('test')->assign('payment.pending');
        States::for($transaction)->within($team)->assign('dispute.open');

        $states = State::query()
            ->forContext($transaction)
            ->forBoundary($team)
            ->inEnvironment('production')
            ->inNamespace('payment')
            ->withState('payment.paid')
            ->get();

        expect($states)->toHaveCount(1)
            ->and($states->first()->name)->toBe('paid')
            ->and($states->first()->namespace)->toBe('payment')
            ->and($states->first()->environment)->toBe('production')
            ->and($states->first()->boundary_id)->toEqual($team->id);
    });
});
