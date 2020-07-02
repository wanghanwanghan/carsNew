<?php

namespace App\Http\Controllers\Business\Index;

use App\Http\Controllers\Business\BusinessBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

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
        dd($this->globalConf());
    }
}
