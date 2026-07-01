<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
