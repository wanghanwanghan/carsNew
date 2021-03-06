<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;

class BusinessBase extends Controller
{
    public function getOrderId($orderWay='微信小程序',$orderType='自驾',$payWay='钱包',$time=1111111111,$userId=1)
    {
        $prefix=$this->orderWay($orderWay).$this->orderType($orderType).$this->payWay($payWay);

        $strTime=substr($time,-8);

        $strUid=str_pad($userId,4,0,STR_PAD_LEFT);

        return $prefix.$strTime.$strUid;
    }

    private function orderWay($orderWay='微信小程序')
    {
        $arr=[
            '未知'=>'0',
            '微信小程序'=>'1',
        ];

        return $arr[$orderWay];
    }

    private function orderType($orderType='自驾')
    {
        $arr=[
            '充值'=>'0',
            '自驾'=>'1',
            '出行'=>'2',
            '摩托'=>'3',
        ];

        return $arr[$orderType];
    }

    private function payWay($payWay='钱包')
    {
        $arr=[
            '待选择'=>'0',
            '钱包'=>'1',
            '微信支付'=>'2',
        ];

        return $arr[$payWay];
    }



}
