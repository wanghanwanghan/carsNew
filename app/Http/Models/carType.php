<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carType extends Model
{
    protected $primaryKey='id';

    protected $table='carType';

    public $timestamps=false;

    protected $guarded=[];
}
