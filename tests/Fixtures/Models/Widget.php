<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives on the "prefixed" connection (table prefix pfx_) to exercise the query
 * runner's logical -> physical table-name rewriting. The connection is only
 * configured in TablePrefixTest, so this model is skipped by discovery
 * elsewhere.
 */
final class Widget extends Model
{
    protected $connection = 'prefixed';

    protected $table = 'widgets';

    protected $guarded = [];

    public $timestamps = false;
}
