<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class users extends Model
{
    protected $primaryKey='id';

    protected $table='users';

    protected $guarded=[];
}
