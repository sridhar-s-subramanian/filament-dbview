<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * Hand-rolled, driver-agnostic lexical analysis of a submitted query. It does
 * NOT try to be a full SQL parser; it exists to answer security questions
 * robustly, without being fooled by keywords hidden inside string literals or
 * comments:
 *
 *   1. Is this exactly one statement (no stacked queries)?
 *   2. Is that statement a read-only SELECT / WITH … SELECT?
 *   3. Which tables does it reference (for scope enforcement)?
 *   4. Which result columns must be force-redacted (alias / expression leaks)?
 *
 * The lexer normalises string literals and comments before keyword scanning so
 * e.g. `SELECT 'DROP TABLE x'` is not flagged and `SELECT 1 -- ; DROP` cannot
 * smuggle a second statement. Quoted identifiers keep their names so table
 * scope can still be enforced on `FROM \`secret\`` / `FROM "secret"`.
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

    /**
     * Keywords that may prefix a real table after FROM / JOIN and are not
     * themselves table names.
     */
    private const TABLE_MODIFIERS = [
        'ONLY' => true,
        'LATERAL' => true,
    ];

    /**
     * Tokens that end a comma-separated table list after FROM / JOIN.
     */
    private const TABLE_LIST_TERMINATORS = [
        'WHERE' => true,
        'GROUP' => true,
        'ORDER' => true,
        'LIMIT' => true,
        'OFFSET' => true,
        'HAVING' => true,
        'UNION' => true,
        'INTERSECT' => true,
        'EXCEPT' => true,
        'FETCH' => true,
        'FOR' => true,
        'WINDOW' => true,
        'RETURNING' => true,
        'ON' => true,
        'USING' => true,
        'JOIN' => true,
        'INNER' => true,
        'LEFT' => true,
        'RIGHT' => true,
        'FULL' => true,
        'CROSS' => true,
        'NATURAL' => true,
        'STRAIGHT_JOIN' => true,
    ];

    public string $stripped;

    public string $firstKeyword;

    public bool $hasTrailingSemicolonOnly;

    public bool $hasStackedStatement;

    public bool $hasExecutableComment;

    /**
     * True when a FROM/JOIN target could not be resolved to a concrete table
     * name (e.g. exotic quoting). Scope checks must fail closed on this.
     */
    public bool $hasUnresolvableTableRef = false;

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

    /**
     * Result column names that must be force-redacted because a select
     * expression references a sensitive source column (including through
     * aliases and nested subqueries). Used to close `password AS pwd` and
     * `hex(password) AS h` style leaks.
     *
     * @return list<string>
     */
    public function sensitiveOutputNames(Redactor $redactor): array
    {
        $tainted = [];

        // Propagate taint through nested select aliases for a few rounds.
        for ($round = 0; $round < 6; $round++) {
            $changed = false;

            foreach ($this->selectLists($this->stripped) as $items) {
                foreach ($items as $item) {
                    if (! $this->expressionIsSensitive($item['expr'], $redactor, $tainted)) {
                        continue;
                    }

                    foreach ($this->outputNamesForItem($item) as $name) {
                        $key = strtolower($name);

                        if (! isset($tainted[$key])) {
                            $tainted[$key] = $name;
                            $changed = true;
                        }
                    }
                }
            }

            if (! $changed) {
                break;
            }
        }

        return array_values($tainted);
    }

    /**
     * 0-based positions in the outermost select list that are sensitive
     * expressions without a usable output name (e.g. `hex(password)` with no
     * alias). Drivers may name these arbitrarily.
     *
     * @return list<int>
     */
    public function sensitiveTopLevelPositions(Redactor $redactor): array
    {
        $tainted = [];

        foreach ($this->sensitiveOutputNames($redactor) as $name) {
            $tainted[strtolower($name)] = $name;
        }

        $lists = $this->selectLists($this->stripped);
        $outer = $lists[0] ?? [];
        $positions = [];

        foreach ($outer as $index => $item) {
            if (! $this->expressionIsSensitive($item['expr'], $redactor, $tainted)) {
                continue;
            }

            if ($this->outputNamesForItem($item) === []) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    private function detectExecutableComment(string $sql): bool
    {
        // MySQL executable comments: /*! ... */ and /*+ ... */ (optimizer hints
        // can smuggle behaviour). Reject either form.
        return preg_match('/\/\*[!+]/', $sql) === 1;
    }

    /**
     * Remove string literals and comments; keep quoted identifier *names* as
     * bare identifiers so table extraction and scope checks still see them.
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

            // SQL Server / Access bracket identifiers: [table]
            if ($char === '[') {
                $i++;
                $content = '';

                while ($i < $length && $sql[$i] !== ']') {
                    // Escaped ]] inside brackets.
                    if ($sql[$i] === ']' && ($i + 1 < $length ? $sql[$i + 1] : '') === ']') {
                        $content .= ']';
                        $i += 2;

                        continue;
                    }

                    $content .= $sql[$i];
                    $i++;
                }

                if ($i < $length && $sql[$i] === ']') {
                    $i++;
                }

                $out .= $this->identifierPlaceholder($content);

                continue;
            }

            // Quoted regions: ' " `
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $i++;
                $content = '';

                while ($i < $length) {
                    $c = $sql[$i];

                    // Backslash escape (MySQL-style) inside single/double quotes.
                    if ($c === '\\' && $quote !== '`') {
                        if ($i + 1 < $length) {
                            $content .= $sql[$i + 1];
                        }
                        $i += 2;

                        continue;
                    }

                    // Doubled quote escape: '' "" ``
                    if ($c === $quote) {
                        if (($i + 1 < $length ? $sql[$i + 1] : '') === $quote) {
                            $content .= $quote;
                            $i += 2;

                            continue;
                        }

                        $i++;

                        break;
                    }

                    $content .= $c;
                    $i++;
                }

                if ($quote === "'") {
                    // Always a string literal.
                    $out .= ' ? ';
                } elseif ($quote === '`') {
                    // Always an identifier (MySQL).
                    $out .= $this->identifierPlaceholder($content);
                } else {
                    // Double quotes: identifier when the content looks like one
                    // (Postgres / ANSI_QUOTES); otherwise a string literal.
                    $out .= $this->looksLikeIdentifier($content)
                        ? $this->identifierPlaceholder($content)
                        : ' ? ';
                }

                continue;
            }

            $out .= $char;
            $i++;
        }

        return $out;
    }

    /**
     * Emit a bare identifier token for a quoted name. Dotted names become
     * schema . table so comma/join extraction can unqualify them.
     */
    private function identifierPlaceholder(string $content): string
    {
        $content = trim($content);

        if ($content === '' || ! $this->looksLikeIdentifier($content)) {
            // Unusable as a table/column name after unquoting — leave a marker
            // that table extraction will treat as unresolvable if it appears
            // where a table is required.
            return ' __unresolvable__ ';
        }

        // Preserve dotted qualification: `db`.`tbl` is already two quoted
        // segments handled separately; a single "db.tbl" quote is rare but
        // emit as-is when it looks like an identifier path.
        return ' ' . $content . ' ';
    }

    private function looksLikeIdentifier(string $content): bool
    {
        return preg_match('/^[A-Za-z_@][A-Za-z0-9_$@.]*(?:\\.[A-Za-z_@][A-Za-z0-9_$@.]*)*$/', $content) === 1;
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
     * Extract every real table referenced after FROM / JOIN, including
     * comma-separated lists (`FROM a, b`). Quoted identifiers are already
     * normalised to bare names by {@see strip()}.
     *
     * @return list<string>
     */
    private function extractTables(string $stripped): array
    {
        $tables = [];

        if (preg_match_all('/\b(?:FROM|JOIN)\b/i', $stripped, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as [$keyword, $offset]) {
            $this->consumeTableList($stripped, $offset + strlen($keyword), $tables);
        }

        return array_values($tables);
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function consumeTableList(string $sql, int $pos, array &$tables): void
    {
        $length = strlen($sql);

        while ($pos < $length) {
            $pos = $this->skipWhitespace($sql, $pos);

            if ($pos >= $length) {
                return;
            }

            // Derived table / subquery: FROM ( SELECT ... ) alias
            if ($sql[$pos] === '(') {
                $pos = $this->skipBalanced($sql, $pos);
                $pos = $this->skipOptionalAlias($sql, $pos);
            } else {
                // Optional modifiers: ONLY, LATERAL
                $word = $this->peekWord($sql, $pos);

                if ($word !== null && isset(self::TABLE_MODIFIERS[strtoupper($word)])) {
                    $pos += strlen($word);
                    $pos = $this->skipWhitespace($sql, $pos);

                    if ($pos < $length && $sql[$pos] === '(') {
                        $pos = $this->skipBalanced($sql, $pos);
                        $pos = $this->skipOptionalAlias($sql, $pos);
                        $pos = $this->skipWhitespace($sql, $pos);

                        if ($pos < $length && $sql[$pos] === ',') {
                            $pos++;

                            continue;
                        }

                        return;
                    }
                }

                $ident = $this->readQualifiedIdentifier($sql, $pos);

                if ($ident === null) {
                    $peek = $this->peekWord($sql, $pos);

                    // Marker left by strip() when a quoted name could not be
                    // turned into a safe identifier — fail closed.
                    if ($peek !== null && strcasecmp($peek, '__unresolvable__') === 0) {
                        $this->hasUnresolvableTableRef = true;
                    }

                    return;
                }

                [$qualified, $pos] = $ident;

                if (strcasecmp($qualified, '__unresolvable__') === 0) {
                    $this->hasUnresolvableTableRef = true;

                    return;
                }

                $table = $this->unqualify($qualified);

                if ($table !== '' && ! $this->isCteName($table)) {
                    $tables[$table] = $table;
                }

                $pos = $this->skipOptionalAlias($sql, $pos);
            }

            $pos = $this->skipWhitespace($sql, $pos);

            if ($pos < $length && $sql[$pos] === ',') {
                $pos++;

                continue;
            }

            // End of this FROM/JOIN table list (ON / WHERE / next JOIN / …).
            return;
        }
    }

    /**
     * @return array{0: string, 1: int}|null  [qualifiedName, newOffset]
     */
    private function readQualifiedIdentifier(string $sql, int $pos): ?array
    {
        $pos = $this->skipWhitespace($sql, $pos);
        $length = strlen($sql);

        if ($pos >= $length) {
            return null;
        }

        if (preg_match('/\G([A-Za-z_@][A-Za-z0-9_$@.]*)/', $sql, $m, 0, $pos) !== 1) {
            return null;
        }

        $name = $m[1];

        // Reject pure terminators accidentally matched (shouldn't happen with
        // the caller checking, but keep extract pure).
        if (isset(self::TABLE_LIST_TERMINATORS[strtoupper($name)]) || strtoupper($name) === 'AS') {
            return null;
        }

        if (isset(self::TABLE_MODIFIERS[strtoupper($name)])) {
            return null;
        }

        return [$name, $pos + strlen($name)];
    }

    private function unqualify(string $qualified): string
    {
        $parts = explode('.', $qualified);
        $table = (string) end($parts);

        return $table;
    }

    private function isCteName(string $table): bool
    {
        foreach ($this->cteNames as $cte) {
            if (strcasecmp($cte, $table) === 0) {
                return true;
            }
        }

        return false;
    }

    private function skipOptionalAlias(string $sql, int $pos): int
    {
        $pos = $this->skipWhitespace($sql, $pos);
        $word = $this->peekWord($sql, $pos);

        if ($word !== null && strcasecmp($word, 'AS') === 0) {
            $pos += strlen($word);
            $pos = $this->skipWhitespace($sql, $pos);
            $ident = $this->readQualifiedIdentifier($sql, $pos);

            return $ident[1] ?? $pos;
        }

        // Alias without AS: identifier that is not a list terminator / join word.
        if ($word !== null
            && ! isset(self::TABLE_LIST_TERMINATORS[strtoupper($word)])
            && ! isset(self::TABLE_MODIFIERS[strtoupper($word)])
            && strcasecmp($word, 'AS') !== 0
            && $sql[$pos] !== ','
            && $sql[$pos] !== ')'
        ) {
            // `FROM posts p` — p is alias. `FROM posts WHERE` — WHERE is terminator.
            $ident = $this->readQualifiedIdentifier($sql, $pos);

            if ($ident !== null) {
                return $ident[1];
            }
        }

        return $pos;
    }

    private function skipWhitespace(string $sql, int $pos): int
    {
        $length = strlen($sql);

        while ($pos < $length && ctype_space($sql[$pos])) {
            $pos++;
        }

        return $pos;
    }

    private function skipBalanced(string $sql, int $pos): int
    {
        $length = strlen($sql);

        if ($pos >= $length || $sql[$pos] !== '(') {
            return $pos;
        }

        $depth = 0;

        while ($pos < $length) {
            $char = $sql[$pos];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                $pos++;

                if ($depth === 0) {
                    return $pos;
                }

                continue;
            }

            $pos++;
        }

        return $pos;
    }

    private function peekWord(string $sql, int $pos): ?string
    {
        $pos = $this->skipWhitespace($sql, $pos);

        if (preg_match('/\G([A-Za-z_@][A-Za-z0-9_$@]*)/', $sql, $m, 0, $pos) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Names introduced by a WITH clause (`WITH x AS (…), y AS (…)` and
     * `WITH x(a,b) AS (…)`). These are derived tables and must be excluded
     * from real-table scope checks.
     *
     * @return list<string>
     */
    private function extractCteNames(string $stripped): array
    {
        $names = [];

        // WITH [RECURSIVE] name [(cols)] AS (
        if (preg_match_all(
            '/(?:\bWITH\b(?:\s+RECURSIVE)?|,)\s+([A-Za-z_][A-Za-z0-9_]*)\s*(?:\([^)]*\))?\s+AS\s*\(/i',
            $stripped,
            $matches,
        ) !== false) {
            foreach ($matches[1] as $name) {
                $names[strtolower($name)] = $name;
            }
        }

        return array_values($names);
    }

    /**
     * Every SELECT-list in the stripped SQL, outermost first. Each item is
     * ['expr' => string, 'alias' => ?string, 'bare' => ?string].
     *
     * @return list<list<array{expr: string, alias: string|null, bare: string|null}>>
     */
    private function selectLists(string $stripped): array
    {
        $lists = [];
        $length = strlen($stripped);
        $offset = 0;

        while ($offset < $length) {
            if (preg_match('/\bSELECT\b/i', $stripped, $m, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                break;
            }

            $selectStart = $m[0][1] + strlen($m[0][0]);
            $fromPos = $this->findTopLevelKeyword($stripped, $selectStart, 'FROM');

            if ($fromPos === null) {
                // SELECT without FROM (e.g. SELECT 1) — still a select list.
                $listSql = rtrim(substr($stripped, $selectStart), " \t\n\r;");
                $offset = $selectStart + strlen($listSql);
            } else {
                $listSql = substr($stripped, $selectStart, $fromPos - $selectStart);
                $offset = $fromPos + 4;
            }

            $lists[] = $this->splitSelectItems($listSql);
        }

        return $lists;
    }

    /**
     * @return list<array{expr: string, alias: string|null, bare: string|null}>
     */
    private function splitSelectItems(string $listSql): array
    {
        $items = [];
        $length = strlen($listSql);
        $start = 0;
        $depth = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $listSql[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $items[] = $this->parseSelectItem(substr($listSql, $start, $i - $start));
                $start = $i + 1;
            }
        }

        $tail = substr($listSql, $start);

        if (trim($tail) !== '') {
            $items[] = $this->parseSelectItem($tail);
        }

        // Drop a leading DISTINCT / ALL / TOP n from the first item.
        if ($items !== []) {
            $items[0]['expr'] = preg_replace(
                '/^\s*(?:DISTINCT|ALL|UNIQUE)(?:\s+ON\s*\([^)]*\))?\s+/i',
                '',
                $items[0]['expr'],
            ) ?? $items[0]['expr'];

            // SQL Server TOP — strip from first item expression.
            $items[0]['expr'] = preg_replace(
                '/^\s*TOP\s+\(?\d+\)?\s+(?:PERCENT\s+)?(?:WITH\s+TIES\s+)?/i',
                '',
                $items[0]['expr'],
            ) ?? $items[0]['expr'];

            // Re-parse bare/alias after stripping prefixes.
            $items[0] = $this->parseSelectItem($items[0]['expr'] . (
                $items[0]['alias'] !== null ? ' AS ' . $items[0]['alias'] : ''
            ));
        }

        return $items;
    }

    /**
     * @return array{expr: string, alias: string|null, bare: string|null}
     */
    private function parseSelectItem(string $item): array
    {
        $item = trim($item);

        if ($item === '' || $item === '*') {
            return ['expr' => $item, 'alias' => null, 'bare' => null];
        }

        // table.*
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*\.\s*\*$/', $item) === 1) {
            return ['expr' => $item, 'alias' => null, 'bare' => null];
        }

        // expr AS alias
        if (preg_match('/^(.*)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/is', $item, $m) === 1) {
            return [
                'expr' => trim($m[1]),
                'alias' => $m[2],
                'bare' => null,
            ];
        }

        // expr alias  (no AS) — only when the left side is not a single bare id
        // that would be the whole expression.
        if (preg_match('/^(.*)\s+([A-Za-z_][A-Za-z0-9_]*)$/s', $item, $m) === 1) {
            $expr = trim($m[1]);
            $alias = $m[2];

            if ($expr !== ''
                && ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\s*\.\s*[A-Za-z_][A-Za-z0-9_]*)*$/', $expr)
            ) {
                return [
                    'expr' => $expr,
                    'alias' => $alias,
                    'bare' => null,
                ];
            }
        }

        // bare or qualified column: [table.]column
        if (preg_match('/^((?:[A-Za-z_][A-Za-z0-9_]*\s*\.\s*)*)([A-Za-z_][A-Za-z0-9_]*)$/', $item, $m) === 1) {
            return [
                'expr' => $item,
                'alias' => null,
                'bare' => $m[2],
            ];
        }

        return ['expr' => $item, 'alias' => null, 'bare' => null];
    }

    /**
     * @param  array{expr: string, alias: string|null, bare: string|null}  $item
     * @return list<string>
     */
    private function outputNamesForItem(array $item): array
    {
        if ($item['alias'] !== null) {
            return [$item['alias']];
        }

        if ($item['bare'] !== null) {
            return [$item['bare']];
        }

        return [];
    }

    /**
     * @param  array<string, string>  $tainted  lowercased name => original
     */
    private function expressionIsSensitive(string $expr, Redactor $redactor, array $tainted): bool
    {
        if (trim($expr) === '' || $expr === '*') {
            return false;
        }

        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\b/', $expr, $matches) === false) {
            return false;
        }

        foreach ($matches[1] as $identifier) {
            if ($redactor->redacts($identifier)) {
                return true;
            }

            if (isset($tainted[strtolower($identifier)])) {
                return true;
            }
        }

        return false;
    }

    private function findTopLevelKeyword(string $sql, int $start, string $keyword): ?int
    {
        $length = strlen($sql);
        $depth = 0;
        $keywordLength = strlen($keyword);

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === '(') {
                $depth++;

                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if (strncasecmp(substr($sql, $i, $keywordLength), $keyword, $keywordLength) !== 0) {
                continue;
            }

            // Word boundaries.
            $before = $i === 0 ? ' ' : $sql[$i - 1];
            $after = $i + $keywordLength < $length ? $sql[$i + $keywordLength] : ' ';

            if (preg_match('/[A-Za-z0-9_]/', $before) === 1) {
                continue;
            }

            if (preg_match('/[A-Za-z0-9_]/', $after) === 1) {
                continue;
            }

            return $i;
        }

        return null;
    }
}
