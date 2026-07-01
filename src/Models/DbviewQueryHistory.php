<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $connection
 * @property string $sql
 * @property int|null $row_count
 * @property float|null $duration_ms
 * @property bool $allowed
 * @property string|null $reason
 */
final class DbviewQueryHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'allowed' => 'boolean',
        'row_count' => 'integer',
        'duration_ms' => 'float',
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('filament-dbview.tables.history', 'dbview_query_history');
    }
}
