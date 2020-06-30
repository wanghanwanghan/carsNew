<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carBrand extends Model
{
    protected $primaryKey='id';

    protected $table='carBrand';

    public $timestamps=false;

    protected $guarded=[];
}
