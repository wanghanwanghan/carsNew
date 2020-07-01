<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carInfo extends Model
{
    protected $primaryKey='id';

    protected $table='carInfo';

    public $timestamps=false;

    protected $guarded=[];

    public function carType()
    {
        return $this->hasOne(carType::class,'id','carType');
    }

    public function carBrand()
    {
        return $this->hasOne(carBrand::class,'id','carBrand');
    }



}
