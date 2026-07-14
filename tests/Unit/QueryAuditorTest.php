<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use SridharSSubramanian\FilamentDbview\Support\QueryAuditor;

it('includes sql in the PSR-3 context when audit.log_sql is true (default)', function (): void {
    config()->set('filament-dbview.audit.log_sql', true);
    config()->set('filament-dbview.features.history', false);

    Log::shouldReceive('getLogger')->andReturn($logger = Mockery::mock(\Psr\Log\LoggerInterface::class));

    $logger->shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'allowed')
                && ($context['sql'] ?? null) === 'select id from posts'
                && array_key_exists('user_id', $context)
                && array_key_exists('connection', $context);
        });

    app(QueryAuditor::class)->record(
        sql: 'select id from posts',
        connection: 'testing',
        rowCount: 3,
        durationMs: 1.5,
        allowed: true,
    );
});

it('omits sql from the PSR-3 context when audit.log_sql is false', function (): void {
    config()->set('filament-dbview.audit.log_sql', false);
    config()->set('filament-dbview.features.history', false);

    Log::shouldReceive('getLogger')->andReturn($logger = Mockery::mock(\Psr\Log\LoggerInterface::class));

    $logger->shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'denied')
                && ! array_key_exists('sql', $context)
                && ($context['reason'] ?? null) === 'not allowed'
                && ($context['allowed'] ?? null) === false;
        });

    app(QueryAuditor::class)->record(
        sql: "select * from users where token = 'secret'",
        connection: 'testing',
        rowCount: null,
        durationMs: 0.0,
        allowed: false,
        reason: 'not allowed',
    );
});
