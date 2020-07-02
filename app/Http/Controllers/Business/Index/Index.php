<?php

namespace App\Http\Controllers\Business\Index;

use App\Http\Controllers\Business\BusinessBase;
use App\Http\Models\banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Yansongda\LaravelPay\Facades\Pay;

class Index extends BusinessBase
{
    private function globalConf()
    {
        $appName=Redis::hget('globalConf','appName');
        $appName=$appName == null ? '超酷的名字' : $appName;

        $logo=Redis::hget('globalConf','logo');
        $logo=$logo == null ? '/static/logo/miniLogo.png' : $logo;

        $tel=Redis::hget('globalConf','tel');
        $tel=$tel == null ? '4008-517-517' : $tel;

        return [
            'appName'=>$appName,
            'tel'=>$tel,
            'logo'=>$logo,
        ];
    }

    public function index(Request $request)
    {
        $order = [
            'out_trade_no' => time(),
            'body' => 'subject-测试',
            'total_fee'      => '1',
            'openid' => 'onkVf1FjWS5SBIixxxxxxxxx',
        ];

        $result = Pay::wechat()->miniapp($order);

        dd($result);





        return response()->json($this->createReturn(200,[
            'globalConf'=>$this->globalConf(),
        ]));
    }
}
