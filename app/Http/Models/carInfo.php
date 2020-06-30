<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carInfo extends Model
{
    protected $primaryKey='id';

    protected $table='carInfo';

    public $timestamps=false;

    protected $guarded=[];
}
