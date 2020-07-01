<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class bannerAction extends Model
{
    protected $primaryKey='id';

    protected $table='bannerAction';

    public $timestamps=false;

    protected $guarded=[];
}
