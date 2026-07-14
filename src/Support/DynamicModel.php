<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for anonymous, read-only Eloquent models bound at runtime to a
 * given table/connection. Wrapping a raw table in a throwaway model lets the
 * browser reuse Filament's Eloquent-bound TableBuilder (search/sort/filter/
 * paginate) while still querying the table directly. Persistence is disabled
 * so the model can never write.
 *
 * The connection is resolved through {@see ConnectionResolver} so optional
 * read-only connection remaps apply to the Database Browser the same way they
 * do to the Query Runner.
 */
abstract class DynamicModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Build a concrete dynamic model instance bound to the given table.
     */
    public static function for(TableInfo $info): Model
    {
        $model = new class extends DynamicModel {};

        $resolver = app(ConnectionResolver::class);
        // Honour connections.read_only remaps (e.g. mysql → mysql_readonly).
        $physical = $resolver->physicalName($info->connection);

        $model->setTable($info->table);
        $model->setConnection($physical);
        $model->setKeyName($info->keyName ?? 'id');
        $model->setKeyType('string');
        $model->incrementing = false;

        return $model;
    }

    /**
     * A bound-to-nothing model for rendering an empty table when no real table
     * is selected. Always paired with a `1 = 0` predicate by the caller.
     */
    public static function blank(): Model
    {
        $model = new class extends DynamicModel {};

        $model->setTable('dbview_blank');
        $model->setKeyName('id');

        return $model;
    }

    public function save(array $options = []): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    /**
     * @param  array<int, mixed>|mixed  $ids
     */
    public static function destroy($ids): int
    {
        return 0;
    }
}
