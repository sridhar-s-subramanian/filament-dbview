<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Centralised access checks. Allow-by-default when no gate ability is
 * configured (host panel auth still applies). Hosts opt into least privilege by
 * pointing config gates at their own Gate abilities — any roles package or
 * custom logic can define those abilities (OWASP A01).
 */
final class Authorization
{
    public static function canAccess(): bool
    {
        return self::passes(config('filament-dbview.authorization.gate'));
    }

    public static function canRunQueries(): bool
    {
        if (! config('filament-dbview.features.query_runner', true)) {
            return false;
        }

        return self::canAccess()
            && self::passes(config('filament-dbview.authorization.query_runner_gate'));
    }

    /**
     * CSV/JSON export from the Query Runner. Off when features.export is false.
     * When export_gate is null, any user who can run queries may export (allow
     * by default). Set export_gate to restrict by role/permission.
     */
    public static function canExport(): bool
    {
        if (! config('filament-dbview.features.export', true)) {
            return false;
        }

        return self::canRunQueries()
            && self::passes(config('filament-dbview.authorization.export_gate'));
    }

    private static function passes(mixed $ability): bool
    {
        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return Gate::forUser(Auth::user())->allows($ability);
    }
}
