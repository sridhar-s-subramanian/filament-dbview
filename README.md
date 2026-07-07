# Filament DB View

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![Total Downloads](https://img.shields.io/packagist/dt/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![PHP Version](https://img.shields.io/packagist/php-v/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](https://packagist.org/packages/sridhar-s-subramanian/filament-dbview)
[![License](https://img.shields.io/packagist/l/sridhar-s-subramanian/filament-dbview.svg?style=flat-square)](LICENSE.md)

An Adminer-like, **strictly read-only** database viewer for [Filament](https://filamentphp.com)
panels. It is scoped to your Laravel app's Eloquent models and gives you two ways
to explore data:

- **Database Browser** — pick any model-backed table and browse it with Filament's
  native table (search, sort, per-column filters, pagination), a full-record
  slide-over, and one-click relationship previews via detected foreign keys.
- **Query Runner** — run ad-hoc `SELECT` queries in an Adminer-style console, with
  `EXPLAIN` / `EXPLAIN ANALYZE`, a table **structure** view (columns, indexes,
  foreign keys), CSV/JSON export, per-user query history, and saved queries.

Everything the viewer can reach is defined by the models it discovers — nothing
else is exposed.

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

The migrations create two small tables (`dbview_query_history`,
`dbview_saved_queries`) used by the Query Runner's history and saved-query
features. If you don't use those features you can skip the migration step and
turn them off in the config.

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
- **Export** results to CSV or JSON, a per-user **query history**, and **saved
  queries**.
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

The Database Browser is always limited to model-backed tables. The Query Runner
defaults to the same, but can be widened to any table on an allowed connection:

```php
$panel->plugin(
    DbviewPlugin::make()
        ->allTables()                                   // query any real table
        ->denyTables(['password_reset_tokens', 'sessions']), // …except these
);
```

`->allTables()` is shorthand for `->queryRunnerScope('connection')`. Read-only
guards and column redaction still apply to every table. These setters take
precedence over the `query_runner` values in the config file.

## Security model (read-only in depth)

Direct database access is guarded on multiple, independent layers — see
`ReadOnlyGuard`:

1. **Lexical allowlist** — only a single `SELECT` / `WITH … SELECT` statement is
   accepted. Stacked statements, executable comments (`/*! … */`, `/*+ … */`), and
   write/DDL/file/DoS tokens (`INSERT`, `UPDATE`, `DROP`, `INTO OUTFILE`,
   `LOAD_FILE`, `pg_read_file`, `SLEEP`, `BENCHMARK`, …) are rejected. Keywords
   hidden inside string literals or comments cannot fool the analyzer.
2. **Table scope** — every referenced table must belong to a discovered model the
   current user is allowed to see. System tables are never reachable.
3. **Enforced `LIMIT`** and **statement timeout** cap runaway queries.
4. **Rolled-back transaction** — reads execute inside a transaction that is always
   rolled back, so nothing can persist even if a layer above were bypassed. This
   also covers `EXPLAIN ANALYZE`, which executes its (SELECT-only) target.
5. **Optional dedicated read-only connection** — route all queries through a
   database user granted only `SELECT` (the strongest control).

Additional controls:

- **Sensitive-column redaction** (`password`, `*_token`, `*_secret`, …) in the
  browser, the runner, and every export.
- **Deny-by-default authorization** via configurable gates (page, query-runner,
  and per-table).
- **Auditing** of every allowed/denied attempt to a PSR-3 channel and the history
  table.

## Configuration

Everything is configured in `config/filament-dbview.php`. The most useful knobs:

```php
'models' => [
    'paths'   => [app_path('Models')], // scanned for Eloquent models (the allowlist)
    'exclude' => [],                    // fully-qualified model classes to skip
    'cache'   => ['enabled' => true, 'ttl' => 3600, /* … */],
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
    'export'               => true,
    'history'              => true,
    'saved_queries'        => true,
    'relationship_preview' => true,      // FK preview actions in the browser
],

'query_runner' => [
    'scope' => 'models',                 // 'models' | 'connection'
    'deny'  => [],                       // blocked even in 'connection' scope
],

'authorization' => [
    'gate'              => null,         // gate checked before any dbview page
    'query_runner_gate' => null,         // additionally guards the SELECT runner
    'table_gate'        => null,         // per-table filter (receives table name)
],

'audit' => ['log_channel' => null],      // PSR-3 channel for audit lines
```

After changing `models.paths` (or your models) with the registry cache enabled,
clear it with:

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
