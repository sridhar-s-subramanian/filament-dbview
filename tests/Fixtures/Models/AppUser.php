<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class AppUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
