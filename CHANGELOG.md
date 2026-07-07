# Changelog

All notable changes to `filament-dbview` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.2.0]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.2...v1.1.0
[1.0.2]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/sridhar-s-subramanian/filament-dbview/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/sridhar-s-subramanian/filament-dbview/releases/tag/v1.0.0
