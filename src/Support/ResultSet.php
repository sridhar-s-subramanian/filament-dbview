<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * A normalised, already-redacted, size-capped result of a read-only query.
 * Everything the UI and exporters need, with no live database handle attached.
 */
final class ResultSet
{
    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly int $rowCount,
        public readonly bool $truncated,
        public readonly float $durationMs,
        public readonly string $connection,
    ) {}

    /**
     * Build a result set from raw database rows, deriving columns from the
     * first row, applying redaction and enforcing the byte cap.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $forceRedactColumns
     * @param  list<int>  $forceRedactPositions
     */
    public static function fromRows(
        array $rows,
        Redactor $redactor,
        string $connection,
        float $durationMs,
        int $maxBytes,
        array $forceRedactColumns = [],
        array $forceRedactPositions = [],
    ): self {
        $columns = $rows === [] ? [] : array_map('strval', array_keys($rows[0]));

        $redacted = [];
        $bytes = 0;
        $truncated = false;

        foreach ($rows as $row) {
            $clean = $redactor->apply($row, $forceRedactColumns, $forceRedactPositions);
            $bytes += strlen((string) json_encode($clean));

            if ($bytes > $maxBytes) {
                $truncated = true;
                break;
            }

            $redacted[] = $clean;
        }

        return new self(
            columns: $columns,
            rows: $redacted,
            rowCount: count($redacted),
            truncated: $truncated,
            durationMs: $durationMs,
            connection: $connection,
        );
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
