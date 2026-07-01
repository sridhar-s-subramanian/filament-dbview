<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Centralised, deny-by-default access checks (OWASP A01). Panels wire real
 * roles/policies by pointing the config gates at their own abilities; with no
 * gate configured a page is accessible (the host panel's own auth still runs).
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

    private static function passes(mixed $ability): bool
    {
        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return Gate::forUser(Auth::user())->allows($ability);
    }
}
