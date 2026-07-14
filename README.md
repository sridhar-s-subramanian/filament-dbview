# Filament DB View

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![Total Downloads](https://img.shields.io/packagist/dt/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![PHP Version](https://img.shields.io/packagist/php-v/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![License](https://img.shields.io/packagist/l/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](LICENSE.md)

An Adminer-like, **read-only** database viewer for [Filament](https://filamentphp.com)
panels. By default it is scoped to your app's Eloquent models and gives you two
ways to explore data:

- **Database Browser** — pick any model-backed table and browse it with Filament's
  native table (search, sort, per-column filters, pagination), a full-record
  slide-over, and one-click relationship previews via detected foreign keys.
- **Query Runner** — run ad-hoc `SELECT` queries in an Adminer-style console, with
  `EXPLAIN` / `EXPLAIN ANALYZE`, a table **structure** view (columns, indexes,
  foreign keys), CSV/JSON export, saved queries, and optional per-user query
  history (feature opt-in; table migration ships with the package).

By default the viewer is scoped to Eloquent models your app discovers. The Query
Runner can optionally list every table on an allowed connection (`->allTables()`)
so you can run `SELECT`s without a model — see [Query Runner scope](#query-runner-scope).

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Filament v4 or v5

## Installation

```bash
composer require sridhar-s-subramanian/filament-dbview
php artisan vendor:publish --tag="filament-dbview-config"
php artisan vendor:publish --tag="filament-dbview-migrations"
php artisan migrate
```

Register the plugin on your panel:

```php
use SridharSSubramanian\FilamentDbview\DbviewPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(DbviewPlugin::make());
}
```

The migrations create two package tables (both ship by default):

- `dbview_saved_queries` — saved Query Runner snippets (feature on by default)
- `dbview_query_history` — storage for per-user query history

**History writes and UI are opt-in** (`features.history` defaults to `false`) so
the table does not grow unbounded on busy panels. The table may still be empty
after migrate until you enable the feature:

```php
$panel->plugin(
    DbviewPlugin::make()
        ->history() // persist + show per-user query history
);
```

PSR-3 audit logging always runs, whether or not history is enabled.

## Features

### Database Browser

A point-and-click browser for one model-backed table at a time — no SQL required:

- Filament's native table with **search**, **click-to-sort**, **column show/hide**
  (remembered per table), and pagination.
- **Adminer-style filters** auto-derived from each column's type (text / number /
  date / boolean), combinable with AND/OR groups.
- **Row detail** slide-over showing the full record with long/JSON values expanded.
- **Relationship previews** — one action per foreign key (`→ Related`) opens the
  related rows in a modal, so you can follow relationships without writing joins.
- A bookmarkable `?table=` URL, so a table can be linked or shared.

### Query Runner

An Adminer-style console for SQL-literate users:

- Run a single read-only `SELECT` / `WITH … SELECT`. Results render in an ad-hoc
  grid with a row-detail slide-over. Press `⌘/Ctrl + Enter` to run.
- **EXPLAIN** and **EXPLAIN ANALYZE** — inspect a query's plan. You never type
  `EXPLAIN`; the typed `SELECT` passes the same read-only guards and only then is a
  driver-appropriate prefix prepended, so the analysed statement is always a single
  SELECT. `EXPLAIN ANALYZE` executes the query to collect real timings, still
  row-capped, timed out, and rolled back.
- **Show structure** — the sidebar lists tables; each has a structure icon that
  shows the table's **columns** (name, type, nullable, default, PK/auto-increment),
  **indexes**, and **foreign keys**, Adminer-style.
- **Export** results to CSV or JSON (on by default; disable with
  `features.export => false`, or restrict with `authorization.export_gate`),
  **saved queries**, and optional per-user **query history** (feature off by
  default; enable with `->history()`).
- A searchable **table sidebar** — click a table name to insert it into the editor,
  the structure icon to inspect it, or the browse link to open it in the Database
  Browser.

### Moving between the two

The two tools are cross-linked so a table flows from one lens to the other without
retyping:

- **Runner → Browser**: model-backed tables in the Runner sidebar have a **Browse**
  link that opens them in the Database Browser.
- **Browser → Runner**: the Browser's **Query** and **Structure** header actions
  open the Query Runner with the current table prefilled (`SELECT * FROM <table>`)
  or on its structure view.

## Query Runner scope

The **Database Browser** is always limited to model-backed tables.

The **Query Runner** defaults to the same (`scope = models`), but can be widened
so operators can run `SELECT`s against **any real table on an allowed
connection** — including tables that have **no Eloquent model**. That is
intentional: use it when you need Adminer-style ad-hoc reads beyond the model
map.

| Scope | How to enable | Tables listed / queryable |
|---|---|---|
| `models` (default) | (default) | Only discovered Eloquent models |
| `connection` | `->allTables()` or `queryRunnerScope('connection')` | Every real table on the connection |

```php
$panel->plugin(
    DbviewPlugin::make()
        ->allTables() // list + allow every table on allowed connections
        // Optional: still block a few sensitive framework tables
        ->denyTables(['password_reset_tokens', 'sessions', 'personal_access_tokens']),
);
```

Details:

- `->allTables()` is shorthand for `->queryRunnerScope('connection')`.
- The deny list is **empty by default** so model-less tables are fully reachable
  when you opt into connection scope. Add `->denyTables([...])` only when you
  want exceptions (e.g. token/session tables).
- Read-only guards, redaction, row limits, timeouts, connection allowlisting,
  and the optional read-only DB remap still apply to every table.
- Fluent plugin setters take precedence over the `query_runner` values in the
  config file.

Query history is **off by default** (the `dbview_query_history` migration still
ships). Enable the feature with `->history()` or `features.history => true` when
you want the Query Runner to persist and re-load per-user queries. PSR-3 audit
logging is separate and always runs — see [Auditing](#auditing).

## Model discovery & registry cache

The package scans your app for concrete Eloquent models and builds a
**registry** (model → table → connection → columns / FKs). That registry is the
default table allowlist for:

- the **Database Browser** (always), and  
- the **Query Runner** when `query_runner.scope` is `models` (the default).

With `->allTables()` / `scope = connection`, the Runner can also list other real
tables on the connection; discovery still drives the Browser and model-backed
labels/links. Per-user `table_gate` filters the registry further when set (see
[Authorization](#authorization-opt-in)).

### Configuration knobs

| Key | Default | Purpose |
|---|---|---|
| `models.paths` | `[app_path('Models')]` | Directories to scan for `*.php` model classes |
| `models.exclude` | `[]` | Fully-qualified class names to **never** register (global) |
| `models.cache.enabled` | `true` (`FILAMENT_DBVIEW_CACHE`) | Cache the registry to avoid filesystem/schema work every request |
| `models.cache.ttl` | `3600` (seconds) | Cache lifetime; `null` = until manually cleared |
| `models.cache.store` | `null` | Cache store name; `null` = default store |
| `models.cache.key` | `filament-dbview.registry` | Cache key |

```php
// config/filament-dbview.php
'models' => [
    'paths' => [
        app_path('Models'),
        // app_path('Domain/Orders/Models'),
    ],

    // Global denylist — these models never appear in the Browser / models-scope Runner
    'exclude' => [
        // \App\Models\PersonalAccessToken::class,
        // \App\Models\Passport\Token::class,
        // \App\Models\Admin::class,
    ],

    'cache' => [
        'enabled' => env('FILAMENT_DBVIEW_CACHE', true),
        'store' => null,
        'key' => 'filament-dbview.registry',
        'ttl' => 3600, // null = cache until filament-dbview:clear
    ],
],
```

### Exclude vs table_gate vs allTables

| Mechanism | Scope | Use when |
|---|---|---|
| `models.exclude` | All users; model never enters the registry | Table should not be browsable as a model at all |
| `authorization.table_gate` | Per user; filters the registry | Some roles may see `orders`, others may not |
| `->allTables()` | Query Runner only | Need `SELECT` on tables **without** an Eloquent model |

### Cache & deploys

With cache **enabled** (default), the registry is stored for `ttl` seconds. After
you:

- add/remove/rename models,  
- change a model’s `$table` or connection,  
- or change `models.paths` / `exclude`,  

run:

```bash
php artisan filament-dbview:clear
```

Add that command to your **deploy script** when registry cache is on in
production, so new models show up immediately instead of waiting for TTL.

For local development you can disable the cache:

```env
FILAMENT_DBVIEW_CACHE=false
```

Abstract models, interfaces, and classes that are not subclasses of
`Illuminate\Database\Eloquent\Model` are ignored. Models whose table/connection
cannot be introspected are skipped (not fatal).

## Auditing

Every Query Runner attempt (allowed or denied) is written as a structured
**PSR-3** log line for accountability. This is **always on** and independent of
query history.

| Destination | Default | Contains full SQL? |
|---|---|---|
| PSR-3 log (`audit.log_channel`) | On (app default logger) | Yes (`audit.log_sql` default `true`) |
| `dbview_query_history` table / UI | Off (`features.history`) | Yes, when history is enabled |

Typical log context:

```text
filament-dbview query allowed|denied
  user_id, connection, allowed, reason, row_count, duration_ms
  sql   ← included when audit.log_sql is true (default)
```

### Configuration

```php
// config/filament-dbview.php
'audit' => [
    // null = Laravel's default logger. Prefer a dedicated channel in production.
    'log_channel' => env('FILAMENT_DBVIEW_LOG_CHANNEL', null),

    // true  = include full SQL in the log context (default; useful for debugging denials).
    // false = metadata only (omit SQL if you treat logs as less trusted than the DB).
    // filter_var so FILAMENT_DBVIEW_LOG_SQL=false in .env is a real boolean false.
    'log_sql' => filter_var(env('FILAMENT_DBVIEW_LOG_SQL', true), FILTER_VALIDATE_BOOLEAN),
],
```

Example dedicated channel in `config/logging.php` (host app):

```php
'channels' => [
    'dbview' => [
        'driver' => 'daily',
        'path' => storage_path('logs/dbview.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
],
```

Then:

```env
FILAMENT_DBVIEW_LOG_CHANNEL=dbview
# FILAMENT_DBVIEW_LOG_SQL=false
```

### Operational notes

- **Full SQL may include secrets** typed into `WHERE` clauses (passwords, tokens,
  PII). Treat the audit log (and history, if enabled) as sensitive.
- Use a **restricted channel** and retention policy when logs leave the app
  server (SIEM, CloudWatch, etc.).
- Setting `log_sql => false` does **not** disable auditing — only the SQL field
  is omitted. Denied attempts still log `reason`, user, and connection.
- Query **history** (when enabled) still stores full SQL so users can re-load
  past queries in the UI; turn history off if you do not want SQL in the database.

## Authorization (opt-in)

Access is **allow by default** for anyone who can open your Filament panel.
This package does **not** ship a roles system and does not depend on Spatie
Permission, Filament Shield, Bouncer, or similar. Panel login is enough unless
you opt in to extra checks.

To restrict by role or permission, point the package at Laravel **Gate** ability
names. Any roles plugin (or your own logic) can define those abilities — the
package only calls `Gate::allows(...)`.

| Config key | Default | When set (opt-in) |
|---|---|---|
| `authorization.gate` | `null` → allow | User must pass this ability to open Browser and Runner |
| `authorization.query_runner_gate` | `null` → allow | Extra ability required for Query Runner only |
| `authorization.table_gate` | `null` → all in-scope tables | Per-table filter; ability receives the **table name** |
| `authorization.export_gate` | `null` → allow if export is on | Extra ability required for CSV/JSON export |

### 1. Publish config (if you have not already)

```bash
php artisan vendor:publish --tag="filament-dbview-config"
```

### 2. Define Gate abilities in your app

Register them in `AppServiceProvider`, `AuthServiceProvider`, or wherever you
define policies — **using whatever permission system you already have**.

**Custom / simple:**

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewDbview', function ($user) {
    return (bool) ($user->is_admin ?? false);
});

Gate::define('runDbviewQueries', function ($user) {
    return (bool) ($user->is_admin ?? false);
});

// Optional: limit which tables appear / can be queried
Gate::define('viewDbviewTable', function ($user, string $table) {
    return in_array($table, ['users', 'orders', 'posts'], true);
});

// Optional: who may download CSV/JSON from the Query Runner
Gate::define('exportDbview', function ($user) {
    return (bool) ($user->is_admin ?? false);
});
```

**Spatie Laravel Permission** (or any package that exposes `$user->can(...)`):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewDbview', fn ($user) => $user->can('dbview.access'));
Gate::define('runDbviewQueries', fn ($user) => $user->can('dbview.query'));
Gate::define('viewDbviewTable', fn ($user, string $table) =>
    $user->can('dbview.tables.*') || $user->can("dbview.tables.{$table}")
);
Gate::define('exportDbview', fn ($user) => $user->can('dbview.export'));
```

Ability names are arbitrary — use whatever strings you prefer, then mirror them
in config.

### 3. Point the package at those abilities

In `config/filament-dbview.php`:

```php
'authorization' => [
    // Leave as null to allow every authenticated panel user (default).
    'gate' => 'viewDbview',

    // Optional: tighter control for the Query Runner (raw SELECT console).
    // If set, the user must pass both `gate` and this ability.
    'query_runner_gate' => 'runDbviewQueries',

    // Optional: hide / block tables the user may not see.
    // The Gate is invoked as: allows('viewDbviewTable', $tableName)
    'table_gate' => 'viewDbviewTable',

    // Optional: who may download CSV/JSON (null = any user who can run queries).
    'export_gate' => 'exportDbview',
],
```

Or via environment-driven config if you prefer:

```php
'authorization' => [
    'gate' => env('FILAMENT_DBVIEW_GATE'),              // null when unset → allow
    'query_runner_gate' => env('FILAMENT_DBVIEW_QUERY_GATE'),
    'table_gate' => env('FILAMENT_DBVIEW_TABLE_GATE'),
    'export_gate' => env('FILAMENT_DBVIEW_EXPORT_GATE'),
],
```

### Export: feature flag vs gate

| Goal | Config |
|---|---|
| Export on for everyone who can use the Query Runner (default) | `features.export => true`, `export_gate => null` |
| Export only for some roles | `features.export => true`, `export_gate => 'exportDbview'` (define the Gate) |
| No export for anyone | `features.export => false` (hides CSV/JSON entirely) |

### Behaviour summary

- **All authorization keys `null`** → any user who can access the Filament panel
  can use DB View (subject to table scope: models vs `allTables()`, etc.),
  including CSV/JSON export when `features.export` is true.
- **`gate` set** → user must pass that ability or both pages are hidden / denied.
- **`query_runner_gate` set** → user must also pass it to open Query Runner;
  Browser still only needs `gate`.
- **`table_gate` set** → sidebar, browser, runner scope, and structure only include
  tables for which the Gate returns true.
- **`export_gate` set** → user must also pass it to download CSV/JSON (and must
  still be allowed to run queries). Export actions re-check the gate server-side.
- **`features.export => false`** → export is off for everyone, regardless of gates.

If you do not need role-based restrictions, leave the authorization section
untouched.

## Security model (read-only in depth)

Direct database access is guarded on multiple, independent layers — see
`ReadOnlyGuard`:

1. **Lexical allowlist** — only a single `SELECT` / `WITH … SELECT` statement is
   accepted. Stacked statements, executable comments (`/*! … */`, `/*+ … */`), and
   many write/DDL/file/DoS tokens (`INSERT`, `UPDATE`, `DROP`, `INTO OUTFILE`,
   `LOAD_FILE`, `SLEEP`, lock helpers, …) are rejected. Strings and comments are
   stripped before keyword scanning. Schema/database-qualified names
   (`other_db.users`) are refused. Prefer a SELECT-only DB user as the strongest
   write barrier (see below).
2. **Table scope** — default `models` scope: only discovered models the user may
   see. `connection` scope (`->allTables()`): any real table on an allowed
   connection (optional `denyTables()`). The Browser is always model-only.
3. **Connection allowlist** — the runner may only use connections derived from
   discovered models (or an explicit `connections.allowed` list).
4. **Enforced `LIMIT`** and **statement timeout** cap runaway queries.
5. **Rolled-back transaction** (Query Runner) — reads run inside a transaction
   that is always rolled back (including `EXPLAIN ANALYZE`). The Browser uses
   Eloquent reads with optional read-only connection remaps and timeouts.
6. **Optional dedicated read-only connection** — map app connections to a DB user
   granted only `SELECT` (recommended for production).

### SQL analysis limits

The Query Runner uses a **lexical analyzer** (`SqlAnalyzer`), not a full SQL
engine grammar. It is built for security questions (one statement? read-only?
which tables? sensitive columns?) and is intentionally fail-closed on many
ambiguous forms.

What it handles well today:

- Single `SELECT` / `WITH … SELECT` (including multiple CTEs and `RECURSIVE`)
- Comma joins and `JOIN`s; quoted identifiers (backticks / `"…"` / `[…]`)
- Rejecting stacked statements, executable comments, many write/DoS tokens
- Rejecting schema/database-qualified table names (`other_db.users`)
- Postgres-style `AS MATERIALIZED` / `AS NOT MATERIALIZED` CTEs

Practical guidance:

- Prefer **bare table names** from the sidebar (logical names the app already
  knows).
- If a complex CTE or dialect-specific form is rejected, simplify the SQL or
  split the query; report persistent false denies with a minimal repro.
- The analyzer is one layer only — pair it with a **SELECT-only database user**
  for the strongest write prevention.

Additional controls:

- **Sensitive-column redaction** (`password`, `*_token`, `*_secret`, …) in the
  browser, the runner, and exports (including common alias/expression cases).
- **Authorization** — allow any panel user by default; optional Laravel Gates
  (see [Authorization (opt-in)](#authorization-opt-in)).
- **Auditing** — every allowed/denied Query Runner attempt is written to a PSR-3
  log channel (see [Auditing](#auditing)); the history table is used only when
  `features.history` is enabled.

## Configuration

Everything is configured in `config/filament-dbview.php`. The most useful knobs:

```php
'models' => [
    'paths'   => [app_path('Models')], // see Model discovery section
    'exclude' => [],                    // FQCNs never registered
    'cache'   => ['enabled' => true, 'ttl' => 3600, /* store, key */],
],

'connections' => [
    'allowed'   => null,                 // null = every connection a model uses
    'read_only' => [],                   // e.g. ['mysql' => 'mysql_readonly']
],

'limits' => [
    'default_rows'     => 100,
    'max_rows'         => 1000,
    'timeout'          => 15,            // statement timeout (seconds)
    'max_result_bytes' => 5 * 1024 * 1024,
],

'redact' => ['password', '*_token', '*_secret', /* … */],

'features' => [
    'query_runner'         => true,
    'explain'              => true,      // EXPLAIN / EXPLAIN ANALYZE buttons
    'structure'            => true,      // "Show structure" (columns/indexes/FKs)
    'export'               => true,      // false = hide CSV/JSON for everyone
    'history'              => false,     // feature opt-in (table still migrates); ->history()
    'saved_queries'        => true,
    'relationship_preview' => true,      // FK preview actions in the browser
],

'query_runner' => [
    'scope' => 'models',                 // 'models' | 'connection' (allTables)
    'deny'  => [],                       // optional blocks in connection scope only
],

// Authorization is allow-by-default; set ability names to opt in (see Authorization section).
'authorization' => [
    'gate'              => null,         // e.g. 'viewDbview'
    'query_runner_gate' => null,         // e.g. 'runDbviewQueries'
    'table_gate'        => null,         // e.g. 'viewDbviewTable' (receives table name)
    'export_gate'       => null,         // e.g. 'exportDbview' (CSV/JSON)
],

'audit' => [
    'log_channel' => null,               // e.g. 'dbview' — dedicated Laravel log channel
    'log_sql'     => true,               // false = omit SQL body from PSR-3 context
],
```

After changing models or discovery config with the registry cache enabled, clear
it (see [Model discovery & registry cache](#model-discovery--registry-cache)):

```bash
php artisan filament-dbview:clear
```

## Development

```bash
composer test        # Pest + Testbench (incl. OWASP security suite)
composer analyse     # PHPStan / Larastan
composer format      # Pint (PER)
composer lint        # PHP_CodeSniffer (PSR-12)
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes per release.

## License

MIT. See [LICENSE.md](LICENSE.md).
