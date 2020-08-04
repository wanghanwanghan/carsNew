<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carLabel extends Model
{
    protected $primaryKey='id';

    protected $table='carLabel';

    public $timestamps=false;

    protected $guarded=[];

}
