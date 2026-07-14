<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Model discovery
    |--------------------------------------------------------------------------
    |
    | Directories scanned for concrete Eloquent Model subclasses. The discovered
    | model => table => connection map is the default allowlist for the Database
    | Browser (always) and for the Query Runner when query_runner.scope is
    | "models". When scope is "connection" (allTables), the runner may also
    | query other real tables on allowed connections (see query_runner below).
    |
    | After changing paths, exclude, or your models (table/connection), run:
    |   php artisan filament-dbview:clear
    | when cache.enabled is true (recommended in deploy scripts). See the README
    | "Model discovery & registry cache" section.
    |
    */

    'models' => [
        'paths' => [
            app_path('Models'),
            // app_path('Domain/Billing/Models'),
        ],

        // Fully-qualified model classes never added to the registry (all users).
        // Prefer this for models that should not appear in the Browser at all;
        // use authorization.table_gate for per-role visibility instead.
        'exclude' => [
            // \App\Models\PersonalAccessToken::class,
            // \App\Models\Passport\Token::class,
            // \App\Models\Admin::class,
        ],

        // Cache the discovered registry to avoid filesystem/schema reflection
        // on every request. Set ttl to null to cache until filament-dbview:clear.
        // Disable in local dev with FILAMENT_DBVIEW_CACHE=false if you prefer.
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

        // CSV/JSON download buttons on the Query Runner. Set false to remove
        // export for everyone. To allow export but only for some roles, leave
        // this true and set authorization.export_gate instead.
        'export' => true,

        // Feature opt-in: when true, every allowed/denied query is written to
        // dbview_query_history and the Query Runner history panel is shown.
        // The table migration still ships; this only controls writes + UI so the
        // table cannot grow unbounded on busy panels. Enable via config or
        // DbviewPlugin::make()->history().
        'history' => false,

        'saved_queries' => true,
        'relationship_preview' => true,
        'structure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query runner scope
    |--------------------------------------------------------------------------
    |
    | The Database Browser is always limited to model-backed tables. The Query
    | Runner can optionally be widened to any table that exists on an allowed
    | connection so operators can SELECT tables that have no Eloquent model:
    |
    |   'models'     => only model-backed tables (default; safest).
    |   'connection' => any real table on an allowed connection, referenced by
    |                   its real (physical) name. Model tables still accept their
    |                   logical name. Read-only guards and column redaction still
    |                   apply to every table. Empty "deny" is intentional so
    |                   model-less tables are fully reachable.
    |
    | "deny" lists tables that stay blocked even in 'connection' scope (matched
    | against both the name typed and the real table name), e.g. framework or
    | secret tables. Empty by default — use it only when you want exceptions.
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
    | Authorization (opt-in — allow by default)
    |--------------------------------------------------------------------------
    |
    | With all values null, any user who can open the Filament panel may use
    | DB View. To restrict by role/permission, set these to Laravel Gate ability
    | names defined in your app (Spatie, Shield, custom, …). See the README
    | "Authorization (opt-in)" section.
    |
    |   gate              — when set, required to open Database Browser and Query Runner
    |   query_runner_gate — extra check for Query Runner only (raw SELECT)
    |   table_gate        — per table; ability receives the table name argument
    |   export_gate       — extra check for CSV/JSON export (null = any query runner user)
    |
    | To disable export entirely for everyone, set features.export => false instead.
    |
    */

    'authorization' => [
        'gate' => null,
        'query_runner_gate' => null,
        'table_gate' => null,
        'export_gate' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditing (OWASP A09)
    |--------------------------------------------------------------------------
    |
    | Every Query Runner attempt (allowed or denied) is written to a PSR-3 log
    | channel. This always runs; it is separate from features.history.
    |
    |   log_channel — Laravel log channel name (null = default app logger).
    |                 Prefer a dedicated channel with restricted access/retention.
    |   log_sql     — when true (default), the full SQL text is included in the
    |                 log context (may contain secrets typed in WHERE clauses).
    |                 Set false to log metadata only (user, connection, allowed,
    |                 reason, row_count, duration). History still stores full SQL
    |                 when features.history is enabled.
    |
    */

    'audit' => [
        'log_channel' => env('FILAMENT_DBVIEW_LOG_CHANNEL', null),
        // filter_var so FILAMENT_DBVIEW_LOG_SQL=false in .env is actually false
        'log_sql' => filter_var(env('FILAMENT_DBVIEW_LOG_SQL', true), FILTER_VALIDATE_BOOLEAN),
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
