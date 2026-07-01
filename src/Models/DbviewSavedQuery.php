<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $connection
 * @property string $sql
 */
final class DbviewSavedQuery extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return $this->table ?? (string) config('filament-dbview.tables.saved_queries', 'dbview_saved_queries');
    }
}
