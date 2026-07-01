<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * Masks values of sensitive columns (passwords, tokens, secrets, …) wherever
 * data leaves the database — browser, query runner, and every export.
 * (OWASP A02: Cryptographic/Sensitive Data Exposure.)
 */
final class Redactor
{
    /** @var list<string> */
    private array $patterns;

    private string $mask;

    /**
     * @param  list<string>|null  $patterns
     */
    public function __construct(?array $patterns = null, ?string $mask = null)
    {
        $this->patterns = array_map(
            'strtolower',
            $patterns ?? (array) config('filament-dbview.redact', []),
        );

        $this->mask = $mask ?? (string) config('filament-dbview.redaction_mask', '••••••••');
    }

    public function redacts(string $column): bool
    {
        $needle = strtolower($column);

        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function mask(): string
    {
        return $this->mask;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function apply(array $row): array
    {
        foreach ($row as $column => $value) {
            if ($value !== null && $this->redacts((string) $column)) {
                $row[$column] = $this->mask;
            }
        }

        return $row;
    }
}
