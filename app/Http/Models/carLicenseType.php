<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class carLicenseType extends Model
{
    protected $primaryKey='id';

    protected $table='carLicenseType';

    public $timestamps=false;

    protected $guarded=[];
}
