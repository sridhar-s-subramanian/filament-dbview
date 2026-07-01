<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Exports;

use SridharSSubramanian\FilamentDbview\Support\ResultSet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an already-redacted {@see ResultSet} to the browser as CSV or JSON.
 * Exports honour the same redaction as the on-screen result because they
 * consume the redacted ResultSet, never the raw database rows.
 */
final class ResultExporter
{
    public static function csv(ResultSet $result, string $filename = 'dbview-export.csv'): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            if ($result->columns !== []) {
                fputcsv($handle, $result->columns);
            }

            foreach ($result->rows as $row) {
                fputcsv($handle, array_map(self::scalar(...), array_values($row)));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public static function json(ResultSet $result, string $filename = 'dbview-export.json'): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            echo json_encode($result->rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]';
        }, $filename, ['Content-Type' => 'application/json']);
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
