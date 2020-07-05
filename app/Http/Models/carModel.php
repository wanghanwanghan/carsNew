<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carModel extends Model
{
    protected $primaryKey='id';

    protected $table='carModel';

    public $timestamps=false;

    protected $guarded=[];

}
