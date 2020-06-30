<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class banner extends Model
{
    protected $primaryKey='id';

    protected $table='banner';

    public $timestamps=false;

    protected $guarded=[];
}
