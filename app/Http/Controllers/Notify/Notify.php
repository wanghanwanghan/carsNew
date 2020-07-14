<?php

namespace App\Http\Controllers\Notify;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Yansongda\LaravelPay\Facades\Pay;

class Notify extends Controller
{
    public function wxNotify(Request $request)
    {
        $pay=Pay::wechat();

        $key='MiniPay_'.Carbon::now()->format('YmdHis');

        try
        {
            $data=$pay->verify();

            Redis::set($key,json_encode($data));

        }catch (\Exception $e)
        {
            Redis::set($key,json_encode($e->getMessage()));
        }

        Redis::expire($key,600);

        return $pay->success()->send();
    }

}
