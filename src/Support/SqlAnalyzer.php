<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * Hand-rolled, driver-agnostic lexical analysis of a submitted query. It does
 * NOT try to be a full SQL parser; it exists to answer three security
 * questions robustly, without being fooled by keywords hidden inside string
 * literals or comments:
 *
 *   1. Is this exactly one statement (no stacked queries)?
 *   2. Is that statement a read-only SELECT / WITH … SELECT?
 *   3. Which tables does it reference (best-effort, for scope enforcement)?
 *
 * The lexer removes string literals, quoted identifiers and comments before
 * any keyword scanning so that e.g. `SELECT 'DROP TABLE x'` is not flagged and
 * `SELECT 1 -- ; DROP` cannot smuggle a second statement.
 */
final class SqlAnalyzer
{
    /**
     * Statement-level keywords that must never appear anywhere in a read-only
     * query (checked as whole words against the stripped SQL).
     */
    public const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE',
        'REPLACE', 'RENAME', 'GRANT', 'REVOKE', 'CALL', 'EXEC', 'EXECUTE',
        'MERGE', 'UPSERT', 'LOCK', 'UNLOCK', 'HANDLER', 'ATTACH', 'DETACH',
        'PRAGMA', 'VACUUM', 'REINDEX', 'COPY', 'IMPORT', 'BACKUP', 'RESTORE',
        'SHUTDOWN', 'KILL', 'INTO', 'DO', 'SET',
    ];

    /**
     * Functions/keywords that read the filesystem, execute shell commands, or
     * enable trivial denial of service — blocked even inside a SELECT.
     */
    public const FORBIDDEN_FUNCTIONS = [
        'LOAD_FILE', 'OUTFILE', 'DUMPFILE', 'LOAD', 'INFILE',
        'PG_READ_FILE', 'PG_LS_DIR', 'PG_READ_BINARY_FILE',
        'LO_IMPORT', 'LO_EXPORT', 'DBLINK', 'XP_CMDSHELL', 'SP_EXECUTESQL',
        'SYS_EXEC', 'SYS_EVAL', 'BENCHMARK', 'SLEEP', 'PG_SLEEP', 'WAITFOR',
        'RANDOMBLOB', 'ZEROBLOB', 'READFILE', 'WRITEFILE', 'EDITBLOB',
    ];

    public string $stripped;

    public string $firstKeyword;

    public bool $hasTrailingSemicolonOnly;

    public bool $hasStackedStatement;

    public bool $hasExecutableComment;

    /** @var list<string> */
    public array $tables;

    /** @var list<string> */
    public array $cteNames;

    public function __construct(public readonly string $raw)
    {
        $this->hasExecutableComment = $this->detectExecutableComment($raw);
        $this->stripped = $this->strip($raw);
        $this->firstKeyword = $this->extractFirstKeyword($this->stripped);
        [$this->hasStackedStatement, $this->hasTrailingSemicolonOnly] = $this->analyseStatements($this->stripped);
        $this->cteNames = $this->extractCteNames($this->stripped);
        $this->tables = $this->extractTables($this->stripped);
    }

    public static function of(string $sql): self
    {
        return new self($sql);
    }

    public function isSelect(): bool
    {
        return in_array($this->firstKeyword, ['SELECT', 'WITH'], true);
    }

    /**
     * @return list<string> whole-word forbidden tokens found in the query
     */
    public function forbiddenTokens(): array
    {
        $found = [];
        $haystack = ' ' . strtoupper($this->stripped) . ' ';

        foreach ([...self::FORBIDDEN_KEYWORDS, ...self::FORBIDDEN_FUNCTIONS] as $token) {
            if (preg_match('/(?<![A-Z0-9_])' . preg_quote($token, '/') . '(?![A-Z0-9_])/', $haystack) === 1) {
                $found[] = $token;
            }
        }

        return array_values(array_unique($found));
    }

    private function detectExecutableComment(string $sql): bool
    {
        // MySQL executable comments: /*! ... */ and /*+ ... */ (optimizer hints
        // can smuggle behaviour). Reject either form.
        return preg_match('/\/\*[!+]/', $sql) === 1;
    }

    /**
     * Remove string literals, quoted identifiers and comments, replacing each
     * with a neutral placeholder so downstream scanning sees only SQL syntax.
     */
    private function strip(string $sql): string
    {
        $out = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comments: -- or #
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                $out .= ' ';

                continue;
            }

            // Block comments: /* ... */
            if ($char === '/' && $next === '*') {
                $i += 2;

                while ($i < $length && ! ($sql[$i] === '*' && ($i + 1 < $length ? $sql[$i + 1] : '') === '/')) {
                    $i++;
                }

                $i += 2;
                $out .= ' ';

                continue;
            }

            // Quoted regions: ' " `
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $i++;

                while ($i < $length) {
                    $c = $sql[$i];

                    // Backslash escape (MySQL-style) inside single/double quotes.
                    if ($c === '\\' && $quote !== '`') {
                        $i += 2;

                        continue;
                    }

                    // Doubled quote escape: '' "" ``
                    if ($c === $quote) {
                        if (($i + 1 < $length ? $sql[$i + 1] : '') === $quote) {
                            $i += 2;

                            continue;
                        }

                        $i++;

                        break;
                    }

                    $i++;
                }

                // Identifiers keep a placeholder word so FROM `tbl` still parses.
                $out .= $quote === '`' ? ' __ident__ ' : ' ? ';

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }

    private function extractFirstKeyword(string $stripped): string
    {
        // Skip leading whitespace and opening parentheses: `( SELECT ... )`.
        $trimmed = ltrim($stripped);
        $trimmed = ltrim($trimmed, "(\t\n\r ");

        if (preg_match('/^([A-Za-z_]+)/', $trimmed, $m) === 1) {
            return strtoupper($m[1]);
        }

        return '';
    }

    /**
     * @return array{0: bool, 1: bool} [hasStackedStatement, hasTrailingSemicolonOnly]
     */
    private function analyseStatements(string $stripped): array
    {
        $trimmed = rtrim($stripped);
        $trailingOnly = false;

        // Allow exactly one trailing semicolon.
        if (str_ends_with($trimmed, ';')) {
            $trimmed = rtrim(substr($trimmed, 0, -1));
            $trailingOnly = true;
        }

        // Any remaining semicolon means a second statement was appended.
        $stacked = str_contains($trimmed, ';');

        return [$stacked, $trailingOnly];
    }

    /**
     * Best-effort table extraction: identifiers following FROM / JOIN. Used for
     * scope checks only; the rolled-back transaction and optional read-only
     * connection are the load-bearing write-prevention controls.
     *
     * @return list<string>
     */
    private function extractTables(string $stripped): array
    {
        $tables = [];

        if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z_][A-Za-z0-9_.$]*)/i', $stripped, $matches) !== false) {
            foreach ($matches[1] as $identifier) {
                if (strcasecmp($identifier, '__ident__') === 0) {
                    // Backtick-quoted table name; unknown after stripping.
                    continue;
                }

                // db.schema.table -> table
                $parts = explode('.', $identifier);
                $table = end($parts);

                if ($table === '') {
                    continue;
                }

                // A common table expression is a derived table, not a real one.
                foreach ($this->cteNames as $cte) {
                    if (strcasecmp($cte, $table) === 0) {
                        continue 2;
                    }
                }

                $tables[$table] = $table;
            }
        }

        return array_values($tables);
    }

    /**
     * Names introduced by a WITH clause (`WITH x AS (…), y AS (…)`). These are
     * derived tables and must be excluded from real-table scope checks.
     *
     * @return list<string>
     */
    private function extractCteNames(string $stripped): array
    {
        $names = [];

        if (preg_match_all('/(?:\bWITH\b(?:\s+RECURSIVE)?|,)\s+([A-Za-z_][A-Za-z0-9_]*)\s+AS\s*\(/i', $stripped, $matches) !== false) {
            foreach ($matches[1] as $name) {
                $names[strtolower($name)] = $name;
            }
        }

        return array_values($names);
    }
}
