<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class order extends Model
{
    protected $primaryKey='id';

    protected $table='order';

    protected $guarded=[];
}
