<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Exceptions;

use RuntimeException;

/**
 * Thrown whenever a submitted query fails any read-only / scope guard. The
 * message is safe to show to the user; it never contains driver internals.
 */
final class UnsafeQueryException extends RuntimeException
{
    public static function notSelect(): self
    {
        return new self('Only a single SELECT (or WITH … SELECT) statement is allowed.');
    }

    public static function multipleStatements(): self
    {
        return new self('Multiple statements are not allowed. Run one SELECT at a time.');
    }

    public static function executableComment(): self
    {
        return new self('Executable comments are not allowed.');
    }

    public static function forbiddenKeyword(string $keyword): self
    {
        return new self("The keyword or function \"{$keyword}\" is not permitted in the read-only viewer.");
    }

    public static function tableNotAllowed(string $table): self
    {
        return new self("The table \"{$table}\" is not exposed by the database viewer.");
    }

    public static function empty(): self
    {
        return new self('The query is empty.');
    }

    public static function unresolvableTableRef(): self
    {
        return new self('A table reference could not be resolved safely. Use unquoted table names from the allowlist.');
    }

    public static function connectionNotAllowed(?string $connection): self
    {
        $name = $connection === null || $connection === '' ? 'default' : $connection;

        return new self("The database connection \"{$name}\" is not available to the database viewer.");
    }

    public static function qualifiedTableRef(string $table): self
    {
        return new self("Schema- or database-qualified table names are not allowed (\"{$table}\"). Use the bare table name from the allowlist.");
    }
}
