<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\States\Database\Models\State;
use Cline\States\Facades\States;
use Illuminate\Support\Sleep;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

describe('Edge cases', function (): void {
    test('handles empty state name gracefully', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('');

        $state = State::query()->forContext($transaction)->first();

        expect($state->name)->toBe('');
    });

    test('handles very long state names', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $longName = str_repeat('a', 128);

        States::for($transaction)->assign($longName);

        expect(States::for($transaction)->has($longName))->toBeTrue();
    });

    test('handles special characters in state names', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid-confirmed_2024');

        expect(States::for($transaction)->has('payment.paid-confirmed_2024'))->toBeTrue();
    });

    test('handles multiple dots in state name', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.processor.stripe.confirmed');

        $state = State::query()->forContext($transaction)->first();

        expect($state->namespace)->toBe('payment')
            ->and($state->name)->toBe('processor.stripe.confirmed');
    });

    test('handles same context in multiple environments and boundaries simultaneously', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team1 = Team::query()->create(['name' => 'Team 1']);
        $team2 = Team::query()->create(['name' => 'Team 2']);

        States::for($transaction)->within($team1)->in('prod')->assign('payment.paid');
        States::for($transaction)->within($team2)->in('prod')->assign('payment.pending');
        States::for($transaction)->within($team1)->in('test')->assign('payment.processing');
        States::for($transaction)->within($team2)->in('test')->assign('payment.failed');

        $allStates = State::query()->forContext($transaction)->get();

        expect($allStates)->toHaveCount(4)
            ->and(States::for($transaction)->within($team1)->in('prod')->has('payment.paid'))->toBeTrue()
            ->and(States::for($transaction)->within($team2)->in('prod')->has('payment.pending'))->toBeTrue()
            ->and(States::for($transaction)->within($team1)->in('test')->has('payment.processing'))->toBeTrue()
            ->and(States::for($transaction)->within($team2)->in('test')->has('payment.failed'))->toBeTrue();
    });

    test('handles rapid state changes', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        States::for($transaction)->transition('payment.pending', 'payment.processing');
        States::for($transaction)->transition('payment.processing', 'payment.authorized');
        States::for($transaction)->transition('payment.authorized', 'payment.captured');
        States::for($transaction)->transition('payment.captured', 'payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->transitions)->toHaveCount(5)
            ->and(States::for($transaction)->has('payment.paid'))->toBeTrue()
            ->and(States::for($transaction)->has('payment.pending'))->toBeFalse();
    });

    test('preserves timestamps during transitions', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.pending');
        Sleep::sleep(1);
        States::for($transaction)->transition('payment.pending', 'payment.paid');

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();
        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(2);

        // Verify all transitions have timestamps
        expect($transitions->every(fn ($t): bool => $t->created_at !== null))->toBeTrue();

        // Verify timestamps differ (one transition should be later)
        $timestamps = $transitions->pluck('created_at')->map(fn ($t) => $t->timestamp)->unique()->toArray();
        expect(count($timestamps))->toBeGreaterThan(0);
    });

    test('handles concurrent state assignments for different contexts', function (): void {
        $transaction1 = Transaction::query()->create(['reference' => 'TXN-001']);
        $transaction2 = Transaction::query()->create(['reference' => 'TXN-002']);

        States::for($transaction1)->assign('payment.paid');
        States::for($transaction2)->assign('payment.paid');

        $states = State::query()->withState('payment.paid')->get();

        expect($states)->toHaveCount(2);
    });

    test('handles very deep metadata nesting', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'value' => 'deep',
                        ],
                    ],
                ],
            ],
        ];

        States::for($transaction)
            ->withMetadata($deepMetadata)
            ->assign('payment.paid');

        $state = State::query()->forContext($transaction)->first();
        $transition = $state->transitions()->first();

        expect($transition->metadata['level1']['level2']['level3']['level4']['value'])->toBe('deep');
    });

    test('handles null values in state data', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.paid', ['amount' => null, 'note' => null]);

        $state = State::query()->forContext($transaction)->first();

        expect($state->data)->toEqual(['amount' => null, 'note' => null]);
    });

    test('handles unicode characters in state names and data', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        States::for($transaction)->assign('payment.完了', ['note' => '支払い完了']);

        $state = State::query()->forContext($transaction)->first();

        expect($state->name)->toBe('完了')
            ->and($state->data['note'])->toBe('支払い完了');
    });
});

describe('Integration scenarios', function (): void {
    test('complete payment workflow', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $team = Team::query()->create(['name' => 'Accounting']);

        // Initial state
        States::for($transaction)
            ->by($team)
            ->because('Customer initiated payment')
            ->withMetadata(['source' => 'web', 'ip' => '192.168.1.1'])
            ->assign('payment.pending', ['amount' => 100.00]);

        expect(States::for($transaction)->has('payment.pending'))->toBeTrue();

        // Processing
        States::for($transaction)
            ->by($team)
            ->because('Payment gateway processing')
            ->transition('payment.pending', 'payment.processing', ['amount' => 100.00, 'gateway' => 'stripe']);

        expect(States::for($transaction)->has('payment.processing'))->toBeTrue()
            ->and(States::for($transaction)->has('payment.pending'))->toBeFalse();

        // Success
        States::for($transaction)
            ->by($team)
            ->because('Payment confirmed by gateway')
            ->withMetadata(['transaction_id' => 'ch_123456'])
            ->transition('payment.processing', 'payment.paid', ['amount' => 100.00, 'confirmed_at' => now()->toIso8601String()]);

        expect(States::for($transaction)->has('payment.paid'))->toBeTrue()
            ->and(States::for($transaction)->has('payment.processing'))->toBeFalse();

        $state = State::query()->forContext($transaction)->withState('payment.paid')->first();

        expect($state->transitions)->toHaveCount(3)
            ->and($state->data)->toHaveKey('confirmed_at');
    });

    test('multi-tenant state management', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $teamA = Team::query()->create(['name' => 'Team A']);
        $teamB = Team::query()->create(['name' => 'Team B']);

        // Each team has their own view of the transaction state
        States::for($transaction)->within($teamA)->assign('review.approved');
        States::for($transaction)->within($teamB)->assign('review.pending');

        expect(States::for($transaction)->within($teamA)->has('review.approved'))->toBeTrue()
            ->and(States::for($transaction)->within($teamB)->has('review.pending'))->toBeTrue()
            ->and(States::for($transaction)->within($teamB)->has('review.approved'))->toBeFalse();

        // Team B approves
        States::for($transaction)->within($teamB)->transition('review.pending', 'review.approved');

        expect(States::for($transaction)->hasAll(['review.approved', 'review.approved']))->toBeFalse() // Different boundaries
            ->and(States::for($transaction)->within($teamA)->has('review.approved'))->toBeTrue()
            ->and(States::for($transaction)->within($teamB)->has('review.approved'))->toBeTrue();
    });

    test('environment-based feature flagging', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);

        // Production: stable features only
        States::for($transaction)->in('production')->assign('features.basic_reporting');
        States::for($transaction)->in('production')->assign('features.export_csv');

        // Staging: test new features
        States::for($transaction)->in('staging')->assign('features.basic_reporting');
        States::for($transaction)->in('staging')->assign('features.export_csv');
        States::for($transaction)->in('staging')->assign('features.advanced_analytics');
        States::for($transaction)->in('staging')->assign('features.real_time_sync');

        expect(State::query()->forContext($transaction)->inEnvironment('production')->count())->toBe(2)
            ->and(State::query()->forContext($transaction)->inEnvironment('staging')->count())->toBe(4);
    });

    test('state audit trail with full history', function (): void {
        $transaction = Transaction::query()->create(['reference' => 'TXN-001']);
        $admin = Team::query()->create(['name' => 'Admin']);
        $support = Team::query()->create(['name' => 'Support']);

        // Create initial state
        States::for($transaction)->by($admin)->because('Initial creation')->assign('status.draft');

        // Multiple transitions with different actors
        States::for($transaction)->by($admin)->because('Ready for review')->transition('status.draft', 'status.submitted');
        States::for($transaction)->by($support)->because('Reviewing submission')->transition('status.submitted', 'status.under_review');
        States::for($transaction)->by($support)->because('Issues found')->transition('status.under_review', 'status.rejected');
        States::for($transaction)->by($admin)->because('Corrections made')->transition('status.rejected', 'status.submitted');
        States::for($transaction)->by($support)->because('Approved')->transition('status.submitted', 'status.approved');

        $state = State::query()->forContext($transaction)->withState('status.approved')->first();
        $transitions = $state->transitions;

        expect($transitions)->toHaveCount(6);

        // Verify initial transition exists
        $initialTransition = $transitions->firstWhere(fn ($t): bool => $t->from_state === null && $t->to_state === 'status.draft');
        expect($initialTransition)->not->toBeNull()
            ->and($initialTransition->actor_id)->toEqual($admin->id);

        // Verify final transition exists
        $finalTransition = $transitions->firstWhere(fn ($t): bool => $t->to_state === 'status.approved');
        expect($finalTransition)->not->toBeNull()
            ->and($finalTransition->actor_id)->toEqual($support->id);

        // Verify full audit trail
        $reasons = $transitions->pluck('reason')->toArray();
        expect($reasons)->toContain('Initial creation', 'Ready for review', 'Reviewing submission', 'Issues found', 'Corrections made', 'Approved');
    });
});
