<?php

namespace App\Http\Controllers\Notify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class Notify extends Controller
{
    public function wxNotify(Request $request)
    {
        Redis::set(json_encode($request->all()));
    }

}
