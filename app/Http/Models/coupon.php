<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class coupon extends Model
{
    protected $primaryKey='id';

    protected $table='coupon';

    public $timestamps=false;

    protected $guarded=[];
}
