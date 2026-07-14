# Changelog

All notable changes to `filament-dbview` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **Security: high-severity hardening.**
  - **Connection allowlist enforced** on every Query Runner execute (blocks
    Livewire clients from pointing at arbitrary Laravel connections).
  - **Schema/database-qualified table names rejected** (`other_db.users`,
    `public.posts`) so an allowed bare name cannot unlock another catalog.
  - **Database Browser** uses `ConnectionResolver` for optional `read_only`
    remaps and applies statement timeouts on browse / relationship previews.
  - **Expanded denylist** for side-effect SELECTs: `GET_LOCK` / advisory locks,
    `pg_terminate_backend`, `OPENROWSET`, and related primitives.

## [1.3.0] - 2026-07-14

### Changed

- **Query history feature is opt-in.** `features.history` defaults to `false` so
  rows are not written (and the UI panel is hidden) unless enabled — the
  `dbview_query_history` migration still ships with the package. Enable with
  `DbviewPlugin::make()->history()` or `features.history => true`. PSR-3 audit
  logging is unchanged and always runs.

### Fixed

- **Security: close critical Query Runner bypasses.**
  - Table scope now covers **comma-separated `FROM` lists** (`FROM allowed, secret`
    no longer skips the second table).
  - **Quoted identifiers** (backticks, double quotes, brackets) keep their real
    names for scope checks; unresolvable table refs fail closed.
  - **Sensitive-column redaction** follows aliases and expressions
    (`password AS pwd`, `hex(password)`, nested alias rewrites), not only
    result column names that match redact patterns.

## [1.2.0] - 2026-07-07

### Added

- **Cross-links between the Database Browser and Query Runner.** The Runner's
  table sidebar shows a **Browse** link on model-backed tables that opens them in
  the Database Browser. The Browser gains **Query** and **Structure** header
  actions that open the Query Runner with the current table prefilled
  (`SELECT * FROM <table>`, on the table's connection) or on its structure view.
- `?table=` / `?structure=` URL parameters on the Query Runner drive the prefill,
  so the links are bookmarkable. Prefilled SQL still passes every read-only guard
  when run, and the structure parameter stays scope-checked.

## [1.1.0] - 2026-07-07

### Added

- **EXPLAIN / EXPLAIN ANALYZE** in the Query Runner. Two toolbar buttons take the
  typed `SELECT`, validate it through the same read-only guards, and only then
  prepend a driver-aware `EXPLAIN` / `EXPLAIN ANALYZE` prefix — so the analysed
  statement is always a single read-only SELECT (never `EXPLAIN ANALYZE DELETE`).
- **Show structure** — an Adminer-style structure view. Each table in the Runner
  sidebar has a structure icon that lists the table's **columns, indexes and
  foreign keys**, gated by the new `features.structure` flag.

### Fixed

- The Query Runner table list is now scoped to the connection's own database
  instead of every schema the connection user can access (Laravel 12 changed
  `getTables()` to span all schemas).
- Schema introspection now resolves the physical table name with the connection
  prefix cleared, so tables on **mixed-prefix** databases (some tables prefixed,
  some not) are described correctly.

## [1.0.2] - 2026-07-02

### Added

- Laravel 13 support.

## [1.0.1] - 2026-07-02

### Added

- Filament v5 support.

## [1.0.0] - 2026-07-02

### Added

- Initial release — a strictly read-only, Adminer-like database viewer for
  Filament panels, scoped to the host application's Eloquent models.
- **Database Browser** — model-scoped table browser built on Filament's native
  table (search, sort, per-column Adminer-style filters, column toggle,
  pagination), a full-record slide-over, and one-click foreign-key relationship
  previews.
- **Query Runner** — an ad-hoc `SELECT` console with CSV/JSON export, per-user
  query history, and saved queries.
- Defence-in-depth read-only security: lexical allowlist, model-backed table
  scope, enforced `LIMIT` + statement timeout, always-rolled-back transactions,
  an optional dedicated read-only connection, sensitive-column redaction,
  deny-by-default authorization gates, and auditing.
- Optional full-database Query Runner scope (`->allTables()` / `->denyTables()`),
  with prefix-resilient handling of prefixed and non-prefixed tables.

[1.3.0]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/sridhar-s-subramanian/filament-dbview/releases/tag/v1.0.0
