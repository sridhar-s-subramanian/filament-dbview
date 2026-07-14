<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * Masks values of sensitive columns (passwords, tokens, secrets, …) wherever
 * data leaves the database — browser, query runner, and every export.
 * (OWASP A02: Cryptographic/Sensitive Data Exposure.)
 *
 * Column-name patterns catch `SELECT password`. Forced names/positions catch
 * alias and expression bypasses such as `password AS pwd` and `hex(password)`.
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
     * @param  list<string>  $forceColumns  output names that must be masked
     * @param  list<int>  $forcePositions  0-based positions that must be masked
     * @return array<string, mixed>
     */
    public function apply(array $row, array $forceColumns = [], array $forcePositions = []): array
    {
        $forceLookup = [];

        foreach ($forceColumns as $name) {
            $forceLookup[strtolower((string) $name)] = true;
        }

        $positionSet = [];

        foreach ($forcePositions as $position) {
            $positionSet[(int) $position] = true;
        }

        $index = 0;

        foreach ($row as $column => $value) {
            $shouldRedact = $value !== null && (
                $this->redacts((string) $column)
                || isset($forceLookup[strtolower((string) $column)])
                || isset($positionSet[$index])
            );

            if ($shouldRedact) {
                $row[$column] = $this->mask;
            }

            $index++;
        }

        return $row;
    }
}
