<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carBelong extends Model
{
    protected $primaryKey='id';

    protected $table='carBelong';

    public $timestamps=false;

    protected $guarded=[];
}
