<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Model discovery
    |--------------------------------------------------------------------------
    |
    | The viewer is scoped to the host application's Eloquent models. These
    | directories are scanned for concrete Model subclasses; the discovered
    | model => table => connection map is the hard allowlist used by both the
    | browser and the query runner. Nothing outside this allowlist is ever
    | reachable (see "table scope" enforcement in ReadOnlyGuard).
    |
    */

    'models' => [
        'paths' => [
            app_path('Models'),
        ],

        // Fully-qualified model classes to exclude from discovery.
        'exclude' => [
            //
        ],

        // Cache the discovered registry to avoid filesystem/schema reflection
        // on every request. Set ttl to null to cache forever (clear manually
        // via the `filament-dbview:clear` command).
        'cache' => [
            'enabled' => env('FILAMENT_DBVIEW_CACHE', true),
            'store' => null, // null = default cache store
            'key' => 'filament-dbview.registry',
            'ttl' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | "allowed" restricts which of the app's database connections the viewer
    | may touch (null = allow every connection referenced by a discovered
    | model). "read_only" optionally forces every query through a dedicated,
    | SELECT-only database connection — the strongest write-prevention control.
    | Map an app connection name to the read-only connection that should be
    | used in its place.
    |
    */

    'connections' => [
        'allowed' => null,

        // e.g. ['mysql' => 'mysql_readonly']
        'read_only' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query limits (DoS hardening)
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'default_rows' => 100,
        'max_rows' => 1000,

        // Statement timeout in seconds, applied where the driver supports it
        // (MySQL MAX_EXECUTION_TIME, PostgreSQL statement_timeout).
        'timeout' => 15,

        // Maximum serialized result size returned to the UI, in bytes.
        'max_result_bytes' => 5 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive data redaction (OWASP A02)
    |--------------------------------------------------------------------------
    |
    | Column names matching any of these patterns (fnmatch, case-insensitive)
    | are masked in the browser, the query runner output, and all exports.
    |
    */

    'redact' => [
        'password',
        'password_*',
        '*_password',
        'remember_token',
        'api_token',
        '*_token',
        '*_secret',
        'secret_*',
        'two_factor_*',
        'private_key',
    ],

    'redaction_mask' => '••••••••',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'query_runner' => true,
        'explain' => true,
        'export' => true,
        'history' => true,
        'saved_queries' => true,
        'relationship_preview' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query runner scope
    |--------------------------------------------------------------------------
    |
    | The Database Browser is always limited to model-backed tables. The Query
    | Runner can optionally be widened to any table that exists on an allowed
    | connection:
    |
    |   'models'     => only model-backed tables (default; safest).
    |   'connection' => any real table on an allowed connection, referenced by
    |                   its real (physical) name. Model tables still accept their
    |                   logical name. Read-only guards and column redaction still
    |                   apply to every table.
    |
    | "deny" lists tables that stay blocked even in 'connection' scope (matched
    | against both the name typed and the real table name), e.g. framework or
    | secret tables. Empty by default.
    |
    | These are the defaults; they can also be set fluently when registering the
    | plugin, which takes precedence:
    |
    |   DbviewPlugin::make()
    |       ->allTables()                       // or ->queryRunnerScope('connection')
    |       ->denyTables(['password_reset_tokens', 'personal_access_tokens']);
    |
    */

    'query_runner' => [
        'scope' => 'models',
        'deny' => [
            // 'password_reset_tokens', 'personal_access_tokens', 'sessions', 'migrations',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization (OWASP A01 — deny by default)
    |--------------------------------------------------------------------------
    |
    | "gate" (if set) is the Gate ability checked before a user may access any
    | dbview page. "query_runner_gate" additionally guards the raw SELECT
    | runner. "table_gate" (if set) is checked per table to filter the registry
    | for the current user; it receives the table name as its argument.
    |
    */

    'authorization' => [
        'gate' => null,
        'query_runner_gate' => null,
        'table_gate' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditing (OWASP A09)
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'log_channel' => env('FILAMENT_DBVIEW_LOG_CHANNEL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Database',
        'sort' => 90,
        'browser_icon' => 'heroicon-o-table-cells',
        'runner_icon' => 'heroicon-o-command-line',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database tables (extras)
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'history' => 'dbview_query_history',
        'saved_queries' => 'dbview_saved_queries',
    ],

];
