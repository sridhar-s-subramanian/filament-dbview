<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

/**
 * Adversarial corpus. Every payload below MUST be blocked by the guard before
 * it can touch the database (OWASP A03 injection / write-prevention, A01 broken
 * access control). A single benign query acts as a positive control so the
 * suite cannot pass by rejecting everything.
 */
function securityGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

dataset('malicious_payloads', [
    'stacked drop' => ['SELECT 1; DROP TABLE users'],
    'stacked delete via newline' => ["SELECT * FROM posts\n; DELETE FROM posts"],
    'comment then stacked' => ["SELECT * FROM posts -- harmless\n; UPDATE posts SET title = 'x'"],
    'mysql executable comment' => ['SELECT /*!32302 1 */ * FROM posts'],
    'optimizer hint comment' => ['SELECT /*+ MAX_EXECUTION_TIME(1) */ * FROM posts'],
    'into outfile' => ["SELECT * FROM users INTO OUTFILE '/tmp/pwn'"],
    'into dumpfile' => ["SELECT title FROM posts INTO DUMPFILE '/tmp/pwn'"],
    'load_file' => ["SELECT LOAD_FILE('/etc/passwd')"],
    'pg_read_file' => ["SELECT pg_read_file('/etc/passwd')"],
    'xp_cmdshell stacked' => ["SELECT 1; EXEC xp_cmdshell 'whoami'"],
    'benchmark dos' => ['SELECT BENCHMARK(100000000, MD5(1))'],
    'sleep dos' => ['SELECT SLEEP(10)'],
    'delete inside cte' => ['WITH x AS (DELETE FROM posts RETURNING *) SELECT * FROM x'],
    'mixed case update' => ["sElEcT * fRoM posts; uPdAtE posts SET title = 'x'"],
    'grant' => ['GRANT ALL ON posts TO evil'],
    'union out of scope table' => ['SELECT id FROM posts UNION SELECT name FROM sqlite_master'],
    'direct system table' => ['SELECT * FROM sqlite_master'],
    'comma join out of scope' => ['SELECT * FROM posts, sqlite_master'],
    'backtick out of scope' => ['SELECT * FROM `sqlite_master`'],
    'double quote out of scope' => ['SELECT * FROM "sqlite_master"'],
    'cross database qualification' => ['SELECT * FROM other_db.users'],
    'get_lock side effect' => ['SELECT GET_LOCK("x", 10)'],
    'pg_advisory_lock' => ['SELECT pg_advisory_lock(42)'],
    'for share lock' => ['SELECT * FROM posts FOR SHARE'],
    'for update lock' => ['SELECT * FROM posts FOR UPDATE'],
    'skip locked' => ['SELECT * FROM posts FOR UPDATE SKIP LOCKED'],
    'lock in share mode' => ['SELECT * FROM posts LOCK IN SHARE MODE'],
]);

it('blocks every malicious payload', function (string $sql): void {
    securityGuard()->run($sql);
})->with('malicious_payloads')->throws(UnsafeQueryException::class);

it('blocks every malicious payload in explain mode', function (string $sql): void {
    securityGuard()->explain($sql);
})->with('malicious_payloads')->throws(UnsafeQueryException::class);

it('blocks every malicious payload in explain-analyze mode', function (string $sql): void {
    securityGuard()->explain($sql, analyze: true);
})->with('malicious_payloads')->throws(UnsafeQueryException::class);

it('positive control: explaining a benign scoped SELECT is allowed', function (): void {
    $result = securityGuard()->explain('SELECT id FROM posts');

    expect($result->rowCount)->toBeGreaterThan(0);
});

it('records denied attempts to the audit history when history is enabled', function (): void {
    config()->set('filament-dbview.features.history', true);

    try {
        securityGuard()->run('DROP TABLE posts');
    } catch (UnsafeQueryException) {
        // expected
    }

    $denied = DB::table('dbview_query_history')->where('allowed', false)->first();

    expect($denied)->not->toBeNull()
        ->and($denied->sql)->toBe('DROP TABLE posts');
});

it('never leaves data modified after a blocked attempt', function (): void {
    $before = DB::table('posts')->count();

    try {
        securityGuard()->run("SELECT * FROM posts; DELETE FROM posts");
    } catch (UnsafeQueryException) {
        // expected
    }

    expect(DB::table('posts')->count())->toBe($before);
});

it('positive control: a benign scoped SELECT is allowed', function (): void {
    $result = securityGuard()->run('SELECT id FROM posts');

    expect($result->rowCount)->toBe(3);
});
