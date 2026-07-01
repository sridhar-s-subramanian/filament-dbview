# Filament DB View

An Adminer-like, **strictly read-only** database viewer for [Filament](https://filamentphp.com)
panels. It is scoped to your Laravel app's Eloquent models and gives you two ways
to explore data:

- **Database Browser** — pick any model-backed table and browse it with Filament's
  native table (search, sort, per-column filters, pagination), plus one-click
  relationship previews via detected foreign keys.
- **Query Runner** — run ad-hoc `SELECT` queries in an Adminer-style console, with
  CSV/JSON export, per-user query history, and saved queries.

Everything the viewer can reach is defined by the models it discovers — nothing
else is exposed.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament v4+

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

### Query Runner scope

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
   rolled back, so nothing can persist even if a layer above were bypassed.
5. **Optional dedicated read-only connection** — route all queries through a
   database user granted only `SELECT` (the strongest control).

Additional controls:

- **Sensitive-column redaction** (`password`, `*_token`, `*_secret`, …) in the
  browser, the runner, and every export.
- **Deny-by-default authorization** via configurable gates (page, query-runner,
  and per-table).
- **Auditing** of every allowed/denied attempt to a PSR-3 channel and the history
  table.

Configure all of the above in `config/filament-dbview.php`.

## Development

```bash
composer test        # Pest + Testbench (incl. OWASP security suite)
composer analyse     # PHPStan / Larastan
composer format      # Pint (PER)
composer lint        # PHP_CodeSniffer (PSR-12)
```

## License

MIT.
