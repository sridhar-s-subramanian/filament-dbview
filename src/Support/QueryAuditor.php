<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SridharSSubramanian\FilamentDbview\Models\DbviewQueryHistory;
use Throwable;

/**
 * Records every query attempt — allowed or denied — for accountability
 * (OWASP A09: Security Logging & Monitoring). Writes a structured PSR-3 log
 * line and, when the history feature is enabled, a per-user history row.
 */
final class QueryAuditor
{
    public function record(
        string $sql,
        string $connection,
        ?int $rowCount,
        float $durationMs,
        bool $allowed,
        ?string $reason = null,
        ?int $userId = null,
    ): void {
        $userId ??= $this->currentUserId();

        $context = [
            'user_id' => $userId,
            'connection' => $connection,
            'allowed' => $allowed,
            'reason' => $reason,
            'row_count' => $rowCount,
            'duration_ms' => round($durationMs, 2),
            'sql' => $sql,
        ];

        $this->logger()->info('filament-dbview query ' . ($allowed ? 'allowed' : 'denied'), $context);

        $this->persistHistory($sql, $connection, $rowCount, $durationMs, $allowed, $reason, $userId);
    }

    private function persistHistory(
        string $sql,
        string $connection,
        ?int $rowCount,
        float $durationMs,
        bool $allowed,
        ?string $reason,
        ?int $userId,
    ): void {
        if (! config('filament-dbview.features.history', false)) {
            return;
        }

        try {
            if (! Schema::hasTable((new DbviewQueryHistory())->getTable())) {
                return;
            }

            DbviewQueryHistory::query()->create([
                'user_id' => $userId,
                'connection' => $connection,
                'sql' => $sql,
                'row_count' => $rowCount,
                'duration_ms' => round($durationMs, 2),
                'allowed' => $allowed,
                'reason' => $reason,
            ]);
        } catch (Throwable) {
            // History is best-effort; auditing must never break a query.
        }
    }

    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    private function logger(): \Psr\Log\LoggerInterface
    {
        $channel = config('filament-dbview.audit.log_channel');

        return is_string($channel) && $channel !== ''
            ? Log::channel($channel)
            : Log::getLogger();
    }
}
