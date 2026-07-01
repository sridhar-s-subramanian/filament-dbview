<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use SridharSSubramanian\FilamentDbview\FilamentDbviewServiceProvider;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedData();

        // Registry is a singleton that memoises; rebuild now the tables exist.
        app(ModelDiscovery::class)->forget();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FilamentDbviewServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filament-dbview.models.paths', [__DIR__ . '/Fixtures/Models']);
        $app['config']->set('filament-dbview.models.cache.enabled', false);
    }

    protected function createSchema(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('api_token')->nullable();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('dbview_query_history', function (Blueprint $table): void {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('connection');
            $table->text('sql');
            $table->integer('row_count')->nullable();
            $table->float('duration_ms')->nullable();
            $table->boolean('allowed')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('dbview_saved_queries', function (Blueprint $table): void {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('name');
            $table->string('connection');
            $table->text('sql');
            $table->timestamps();
        });
    }

    protected function seedData(): void
    {
        \Illuminate\Support\Facades\DB::table('users')->insert([
            ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'hunter2', 'api_token' => 'secret-token'],
            ['id' => 2, 'name' => 'Linus', 'email' => 'linus@example.com', 'password' => 'toralvds', 'api_token' => 'tok2'],
        ]);

        \Illuminate\Support\Facades\DB::table('posts')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'Hello', 'body' => 'First'],
            ['id' => 2, 'user_id' => 1, 'title' => 'Second', 'body' => null],
            ['id' => 3, 'user_id' => 2, 'title' => 'Kernel', 'body' => 'Boot'],
        ]);
    }
}
