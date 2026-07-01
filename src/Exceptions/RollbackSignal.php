<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Exceptions;

use RuntimeException;

/**
 * Internal control-flow exception used to force a database transaction to roll
 * back after a read-only query has fetched its rows. Never leaves the guard.
 */
final class RollbackSignal extends RuntimeException {}
