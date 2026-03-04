<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\States\StatesServiceProvider;
use Cline\VariableKeys\Enums\MorphType;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Cline\VariableKeys\Facades\VariableKeys;
use Cline\VariableKeys\VariableKeysServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Fixtures\Team;
use Tests\Fixtures\Transaction;

use function config;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VariableKeysServiceProvider::class,
            StatesServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('states.primary_key_type', 'id')) ?? PrimaryKeyType::ID;
        $contextMorphType = MorphType::tryFrom(config('states.context_morph_type', 'string')) ?? MorphType::String;
        $boundaryMorphType = MorphType::tryFrom(config('states.boundary_morph_type', 'string')) ?? MorphType::String;
        $actorMorphType = MorphType::tryFrom(config('states.actor_morph_type', 'string')) ?? MorphType::String;

        Schema::dropIfExists('state_transitions');
        Schema::dropIfExists('states');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('teams');

        Schema::create('states', function (Blueprint $table) use ($primaryKeyType, $contextMorphType, $boundaryMorphType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };

            $table->string('context_type', 128);
            match ($contextMorphType) {
                MorphType::ULID => $table->ulid('context_id'),
                MorphType::UUID => $table->uuid('context_id'),
                MorphType::Numeric => $table->unsignedBigInteger('context_id'),
                MorphType::String => $table->unsignedBigInteger('context_id'),
            };
            $table->index(['context_type', 'context_id']);

            $table->string('namespace', 128)->nullable();
            $table->string('name', 128);
            $table->string('environment', 128)->default('default');
            $table->index(['context_type', 'context_id', 'namespace', 'environment']);

            $table->string('boundary_type', 128)->nullable();
            match ($boundaryMorphType) {
                MorphType::ULID => $table->ulid('boundary_id')->nullable(),
                MorphType::UUID => $table->uuid('boundary_id')->nullable(),
                MorphType::Numeric => $table->unsignedBigInteger('boundary_id')->nullable(),
                MorphType::String => $table->unsignedBigInteger('boundary_id')->nullable(),
            };
            $table->index(['boundary_type', 'boundary_id']);

            $table->json('data')->nullable();

            $table->timestamps();

            $table->unique([
                'context_type',
                'context_id',
                'namespace',
                'name',
                'environment',
                'boundary_type',
                'boundary_id',
            ], 'states_unique_context_state');
        });

        Schema::create('state_transitions', function (Blueprint $table) use ($primaryKeyType, $actorMorphType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };

            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->foreignUlid('state_id')->constrained()->cascadeOnDelete(),
                PrimaryKeyType::UUID => $table->foreignUuid('state_id')->constrained()->cascadeOnDelete(),
                PrimaryKeyType::ID => $table->foreignId('state_id')->constrained()->cascadeOnDelete(),
            };

            $table->string('from_state', 128)->nullable();
            $table->string('to_state', 128);

            $table->string('actor_type', 128)->nullable();
            match ($actorMorphType) {
                MorphType::ULID => $table->ulid('actor_id')->nullable(),
                MorphType::UUID => $table->uuid('actor_id')->nullable(),
                MorphType::Numeric => $table->unsignedBigInteger('actor_id')->nullable(),
                MorphType::String => $table->unsignedBigInteger('actor_id')->nullable(),
            };
            $table->index(['actor_type', 'actor_id']);

            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');
            $table->index(['state_id', 'created_at']);
        });

        // Create test fixtures
        Schema::create('transactions', function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };
            $table->string('reference');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };
            $table->string('name');
            $table->timestamps();
        });

        // Register test fixtures with VariableKeys
        VariableKeys::map([
            Transaction::class => [
                'primary_key_type' => $primaryKeyType,
            ],
            Team::class => [
                'primary_key_type' => $primaryKeyType,
            ],
        ]);
    }
}
