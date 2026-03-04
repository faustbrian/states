<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Database\Models\State;
use Cline\States\Exceptions\InvalidTransitionException;
use Cline\States\Facades\States;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

describe('TransitionConductor::by()', function (): void {
    test('records actor when transitioning', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Admin Team']);

        States::for($transaction)->by($team)->assign('payment.pending');

        $state = State::query()->forContext($transaction)->withState('payment.pending')->first();
        $transition = $state->transitions()->first();

        expect($transition->actor_type)->toBe($team->getMorphClass())
            ->and($transition->actor_id)->toEqual($team->id);
    });

    test('handles null actor', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->by(null)->assign('payment.pending');

        $state = State::query()->forContext($transaction)->withState('payment.pending')->first();
        $transition = $state->transitions()->first();

        expect($transition->actor_type)->toBeNull()
            ->and($transition->actor_id)->toBeNull();
    });

    test('actor persists across multiple operations', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Admin Team']);

        $conductor = States::for($transaction)->by($team);
        $conductor->assign('payment.pending');
        $conductor->transition('payment.pending', 'payment.paid');

        $state = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->first();

        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(2);
        expect($transitions->every(fn ($t): bool => $t->actor_id === $team->id))->toBeTrue();
    });
});

describe('TransitionConductor::because()', function (): void {
    test('records reason when transitioning', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)
            ->because('Customer requested refund')
            ->assign('payment.refunded');

        $state = State::query()->forContext($transaction)->withState('payment.refunded')->first();
        $transition = $state->transitions()->first();

        expect($transition->reason)->toBe('Customer requested refund');
    });

    test('reason persists across operations', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        $conductor = States::for($transaction)->because('Batch processing');
        $conductor->assign('payment.pending');
        $conductor->transition('payment.pending', 'payment.paid');

        $state = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->first();

        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(2);
        expect($transitions->every(fn ($t): bool => $t->reason === 'Batch processing'))->toBeTrue();
    });
});

describe('TransitionConductor::withMetadata()', function (): void {
    test('stores metadata with transition', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $metadata = ['amount' => 100.50, 'currency' => 'USD', 'ip' => '192.168.1.1'];

        States::for($transaction)
            ->withMetadata($metadata)
            ->assign('payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();
        $transition = $state->transitions()->first();

        expect($transition->metadata)->toEqual($metadata);
    });

    test('metadata persists across operations', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $metadata = ['processor' => 'stripe', 'gateway_id' => 'ch_123'];

        $conductor = States::for($transaction)->withMetadata($metadata);
        $conductor->assign('payment.pending');
        $conductor->transition('payment.pending', 'payment.paid');

        $state = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->first();

        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(2);
        expect($transitions->every(fn ($t): bool => $t->metadata === $metadata))->toBeTrue();
    });

    test('handles empty metadata', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)
            ->withMetadata([])
            ->assign('payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();
        $transition = $state->transitions()->first();

        expect($transition->metadata)->toEqual([]);
    });
});

describe('TransitionConductor::assign()', function (): void {
    test('creates state with data', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $data = ['amount' => 500.00, 'note' => 'Initial payment'];

        States::for($transaction)->assign('payment.paid', $data);

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->data)->toEqual(['amount' => 500, 'note' => 'Initial payment']);
    });

    test('handles null data', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->data)->toBeNull();
    });

    test('reassigning same state does not duplicate', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->assign('payment.paid');

        $count = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->count();

        expect($count)->toBe(1);
    });

    test('supports non-namespaced states', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('active');

        expect(States::for($transaction)->has('active'))->toBeTrue();

        $state = State::query()->forContext($transaction)->withState('active')->first();

        expect($state->namespace)->toBeNull()
            ->and($state->name)->toBe('active');
    });
});

describe('TransitionConductor::transition()', function (): void {
    test('preserves all transition history', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.processing');
        States::for($transaction)->transition('payment.processing', 'payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();
        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(3);

        $toStates = $transitions->pluck('to_state')->toArray();
        expect($toStates)->toContain('payment.pending', 'payment.processing', 'payment.paid');
    });

    test('updates state data during transition', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending', ['amount' => 100]);
        States::for($transaction)->transition('payment.pending', 'payment.paid', ['amount' => 100, 'confirmed' => true]);

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->data)->toEqual(['amount' => 100, 'confirmed' => true]);
    });
});

describe('TransitionConductor::remove()', function (): void {
    test('removes state and returns true', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');

        $result = States::for($transaction)->remove('payment.paid');

        expect($result)->toBeTrue()
            ->and(States::for($transaction)->has('payment.paid'))->toBeFalse();
    });

    test('returns false when state does not exist', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        $result = States::for($transaction)->remove('payment.paid');

        expect($result)->toBeFalse();
    });

    test('does not leave orphaned states', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid');
        States::for($transaction)->remove('payment.paid');

        $count = State::query()
            ->forContext($transaction)
            ->withState('payment.paid')
            ->count();

        expect($count)->toBe(0);
    });
});

describe('TransitionConductor fluent chaining', function (): void {
    test('chains all methods together', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Admin']);

        States::for($transaction)
            ->by($team)
            ->because('Initial setup')
            ->withMetadata(['source' => 'api'])
            ->assign('payment.pending', ['amount' => 250]);

        $state = State::query()->forContext($transaction)->withState('payment.pending')->first();
        $transition = $state->transitions()->first();

        expect($state->data)->toEqual(['amount' => 250])
            ->and($transition->actor_id)->toEqual($team->id)
            ->and($transition->reason)->toBe('Initial setup')
            ->and($transition->metadata)->toEqual(['source' => 'api']);
    });
});

describe('State machine validation', function (): void {
    test('allows transitions when no machine configured', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.paid');

        expect(States::for($transaction)->has('payment.paid'))->toBeTrue();
    });

    test('allows transitions when machine configured with allowed transition', function (): void {
        config([
            'states.machines' => [
                Transaction::class => [
                    'transitions' => [
                        'payment.pending' => ['payment.processing', 'payment.cancelled'],
                        'payment.processing' => ['payment.paid', 'payment.failed'],
                    ],
                ],
            ],
        ]);

        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.processing');
        States::for($transaction)->transition('payment.processing', 'payment.paid');

        expect(States::for($transaction)->has('payment.paid'))->toBeTrue();
    });

    test('throws exception when transition not allowed by machine configuration', function (): void {
        config([
            'states.machines' => [
                Transaction::class => [
                    'transitions' => [
                        'payment.pending' => ['payment.processing'],
                        'payment.processing' => ['payment.paid'],
                    ],
                ],
            ],
        ]);

        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');

        expect(fn () => States::for($transaction)->transition('payment.pending', 'payment.cancelled'))
            ->toThrow(InvalidTransitionException::class, "Transition from 'payment.pending' to 'payment.cancelled' is not allowed by state machine configuration.");
    });

    test('allows transitions when machine configured but no transitions defined', function (): void {
        config([
            'states.machines' => [
                Transaction::class => [],
            ],
        ]);

        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.paid');

        expect(States::for($transaction)->has('payment.paid'))->toBeTrue();
    });

    test('allows transitions when machine configured with empty transitions array', function (): void {
        config([
            'states.machines' => [
                Transaction::class => [
                    'transitions' => [],
                ],
            ],
        ]);

        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.paid');

        expect(States::for($transaction)->has('payment.paid'))->toBeTrue();
    });
});
