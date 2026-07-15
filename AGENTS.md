# AGENTS.md — Filament DB View

Instructions for AI coding agents integrating or configuring
`sridhar-s-subramanian/filament-dbview` in a host Laravel + Filament app.

**Full human docs:** [README.md](README.md). Prefer this file for a configuration
checklist; open the README when you need narrative detail or examples.

---

## What this package is

- Read-only **Database Browser** + **Query Runner** for Filament panels.
- Default table allowlist = discovered Eloquent models under `models.paths`.
- Query Runner can optionally query **any** table on an allowed connection
  (`->allTables()`), including tables **without** models.
- **Not** a full DBA tool: only `SELECT` / `WITH … SELECT`; writes are blocked
  in application code. Strongest write prevention is still a **SELECT-only DB
  user** via `connections.read_only`.

---

## Minimal install (host app)

```bash
composer require sridhar-s-subramanian/filament-dbview
php artisan vendor:publish --tag="filament-dbview-config"
php artisan vendor:publish --tag="filament-dbview-migrations"
php artisan migrate
```

Register on the Filament panel:

```php
use SridharSSubramanian\FilamentDbview\DbviewPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(DbviewPlugin::make());
}
```

Config file after publish: `config/filament-dbview.php`.

Migrations always ship both tables:

| Table | Feature default |
|---|---|
| `dbview_saved_queries` | Saved queries **on** |
| `dbview_query_history` | History **off** (table may stay empty) |

---

## Defaults agents must not “fix”

| Setting | Default | Meaning |
|---|---|---|
| Authorization gates | all `null` | **Allow** any user who can open the Filament panel |
| `features.history` | `false` | No history UI/writes until enabled |
| `features.export` | `true` | CSV/JSON on for anyone who can run queries |
| `query_runner.scope` | `models` | Only model-backed tables in the runner |
| `query_runner.deny` | `[]` | Empty deny is **intentional** with `allTables()` |
| `audit.log_sql` | `true` | Full SQL in PSR-3 audit logs |
| Model registry cache | `true` (TTL 3600s) | Clear after model/path changes |

Do **not** invent a roles package dependency. Use Laravel **Gate** ability names
only when the host wants restrictions.

---

## Configuration map

### Plugin fluent API

```php
DbviewPlugin::make()
    ->history()                    // features.history = true
    ->allTables()                  // query_runner.scope = connection
    ->denyTables(['sessions', 'password_reset_tokens', 'personal_access_tokens']);
```

Fluent setters override config for scope/deny/history.

### Important config keys

| Key | Purpose |
|---|---|
| `models.paths` | Directories to scan for Eloquent models |
| `models.exclude` | FQCN list never registered (global) |
| `models.cache.*` | Registry cache; clear with `php artisan filament-dbview:clear` |
| `connections.allowed` | `null` = connections used by discovered models |
| `connections.read_only` | Map app connection → SELECT-only connection name |
| `limits.*` | default/max rows, statement timeout, max result bytes |
| `redact` / `redaction_mask` | Sensitive column name patterns |
| `features.query_runner` | Enable Query Runner page |
| `features.export` | `false` = hide CSV/JSON for everyone |
| `features.history` | Persist/show per-user query history |
| `features.structure` | Structure UI |
| `query_runner.scope` | `models` \| `connection` |
| `query_runner.deny` | Tables blocked even in connection scope |
| `authorization.gate` | Gate ability for Browser (+ Runner base) |
| `authorization.query_runner_gate` | Extra ability for raw SQL runner |
| `authorization.table_gate` | Per-table ability (arg = table name) |
| `authorization.export_gate` | Extra ability for CSV/JSON export |
| `audit.log_channel` | Laravel log channel name (`null` = default) |
| `audit.log_sql` | Include SQL text in audit logs |

Env helpers (see published config):

- `FILAMENT_DBVIEW_CACHE`
- `FILAMENT_DBVIEW_LOG_CHANNEL`
- `FILAMENT_DBVIEW_LOG_SQL` (use `filter_var` / boolean-aware parsing in config)

---

## Authorization (opt-in)

**Allow by default.** Panel login is enough until the host sets gates.

1. Define Gate abilities in the **host app** (Spatie, Shield, custom, …).
2. Set ability **names** in `authorization.*`.

```php
// Host app
Gate::define('viewDbview', fn ($user) => $user->can('dbview.access'));
Gate::define('runDbviewQueries', fn ($user) => $user->can('dbview.query'));
Gate::define('viewDbviewTable', fn ($user, string $table) => /* … */);
Gate::define('exportDbview', fn ($user) => $user->can('dbview.export'));
```

```php
// config/filament-dbview.php
'authorization' => [
    'gate' => 'viewDbview',
    'query_runner_gate' => 'runDbviewQueries',
    'table_gate' => 'viewDbviewTable',
    'export_gate' => 'exportDbview',
],
```

| Goal | Config |
|---|---|
| Any panel user | leave all gates `null` |
| Restrict pages | set `gate` |
| Restrict raw SQL only | set `query_runner_gate` |
| Restrict export only | set `export_gate` (keep `features.export` true) |
| No export at all | `features.export => false` |

Details: README → **Authorization (opt-in)**.

---

## Query Runner scope

| Scope | How | Tables |
|---|---|---|
| `models` (default) | — | Discovered models only |
| `connection` | `->allTables()` | Every real table on allowed connections |

Empty `deny` with `allTables()` is **by design** (model-less tables). Optionally:

```php
->denyTables(['password_reset_tokens', 'sessions', 'personal_access_tokens']);
```

Browser is **always** model-scoped.

Details: README → **Query Runner scope**.

---

## Auditing vs history

| Path | Default | Full SQL? |
|---|---|---|
| PSR-3 log | **Always on** | Yes unless `audit.log_sql => false` |
| History table/UI | **Off** | Yes when `features.history` / `->history()` |

Treat audit logs as sensitive if `log_sql` is true. Prefer a dedicated
`audit.log_channel` in production.

Details: README → **Auditing**.

---

## Model discovery ops

- Exclude sensitive models via `models.exclude` (global) vs `table_gate` (per user).
- After changing models/paths/exclude with cache on:

```bash
php artisan filament-dbview:clear
```

Add that to deploy scripts when `models.cache.enabled` is true.

Local: `FILAMENT_DBVIEW_CACHE=false` if preferred.

Details: README → **Model discovery & registry cache**.

---

## Production checklist (agents configuring a host)

- [ ] Plugin registered on the correct Filament panel  
- [ ] Config + migrations published; `migrate` run  
- [ ] `connections.read_only` mapped to a SELECT-only DB user if possible  
- [ ] Gates set if multi-role panel (do not assume Spatie is installed)  
- [ ] History left off unless product needs it (`->history()` only then)  
- [ ] If using `allTables()`, consider `denyTables([...])` for secrets/framework tables  
- [ ] Audit channel restricted; set `log_sql => false` if logs are widely visible  
- [ ] Deploy runs `filament-dbview:clear` when registry cache is enabled  
- [ ] Sensitive models in `models.exclude` if they must never appear in Browser  

---

## Working on this package (contributors)

```bash
composer test      # Pest
composer analyse   # PHPStan / Larastan
composer format    # Pint
composer lint      # PHPCS PSR-12
```

- Namespace: `SridharSSubramanian\FilamentDbview\`
- Security-sensitive: `SqlAnalyzer`, `ReadOnlyGuard`, `Authorization`, `Redactor`
- Prefer fail-closed on ambiguous SQL; keep allow-by-default product semantics
  for gates/features unless docs say opt-in

---

## Do not

- Depend on a specific roles package inside this package  
- Enable history by default in host apps without an explicit product decision  
- Log/export secrets: remind hosts that SQL in logs/history can contain literals  
- Use schema-qualified table names in runner SQL (`other_db.users` is rejected)  
- Assume Browser uses rolled-back transactions (Runner does; Browser uses RO remap + timeout)

---

## README index

| Topic | Section |
|---|---|
| Install / plugin | Installation |
| Browser / Runner features | Features |
| `allTables` / deny | Query Runner scope |
| Discovery / cache | Model discovery & registry cache |
| Gates / export | Authorization (opt-in) |
| Logs / `log_sql` | Auditing |
| Guards / analyzer limits | Security model |
| Config dump | Configuration |
