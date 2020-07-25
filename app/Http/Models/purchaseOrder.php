<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class purchaseOrder extends Model
{
    protected $primaryKey='id';

    protected $table='purchaseOrder';

    protected $guarded=[];
}
