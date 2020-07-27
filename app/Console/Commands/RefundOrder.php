<?php

namespace App\Console\Commands;

use App\Http\Models\order;
use App\Http\Models\refundInfo;
use App\Http\Models\users;
use App\Http\Service\MiniAppPay;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RefundOrder extends Command
{
    protected $signature = 'refundOrder';

    protected $description = '自动退款';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //确定对象
        $refundOrders=refundInfo::where('refundTime','<',time())->where('isFinish',0)->get()->toArray();

        foreach ($refundOrders as $oneRefundOrder)
        {
            $orderId=$oneRefundOrder['orderId'];

            $orderInfo=order::where('orderId',$orderId)->first()->toArray();

            //退款到钱包还是微信
            $payWay=$orderInfo['payWay'];

            //退全款就是退单 车损 违章
            $refundType=$oneRefundOrder['refundType'];

            switch ($refundType)
            {
                case '1':
                    $this->all($orderInfo,$oneRefundOrder,$payWay);
                    break;
                case '2':
                    $this->damage($orderInfo,$oneRefundOrder,$payWay);
                    break;
                case '3':
                    $this->forfeit($orderInfo,$oneRefundOrder,$payWay);
                    break;
            }
        }

        return true;
    }

    private function wallet($orderInfo,$refMoney)
    {
        $phone=$orderInfo['account'];

        $userInfo=users::where('phone',$phone)->first();

        $userInfo->money+=$refMoney;

        $userInfo->save();

        return true;
    }

    private function wx($orderInfo,$refundInfo,$refMoney)
    {
        $body='退款-'.time();

        $payment=$orderInfo['payment'];//只有全款和违章押金

        $payment==='全款' ?
            $totalFee=$orderInfo['orderPrice']+$orderInfo['damagePrice']+$orderInfo['forfeitPrice'] :
            $totalFee=$orderInfo['forfeitPrice'];

        $res=MiniAppPay::getInstance()->refundOrder($orderInfo['orderId'],$refundInfo['refundId'],$body,$totalFee,$refMoney);

        Redis::set("{$orderInfo['orderId']}_{$refundInfo['refundInfo']}",json_encode($res));
        Redis::expire("{$orderInfo['orderId']}_{$refundInfo['refundInfo']}",86400);

        return true;
    }

    //退全款
    private function all($orderInfo,$refundInfo,$payWay)
    {
        //看看这个订单还差多少没退

        //车损退了多少
        $damageRefund=refundInfo::where(['orderId'=>$orderInfo['orderId'],'refundType'=>2])
            ->select(DB::raw('sum(refundPrice) as refundPrice'))->get()->toArray();
        $damageRefund=(int)head(Arr::flatten($damageRefund));

        //违章退了多少
        $forfeitRefund=refundInfo::where(['orderId'=>$orderInfo['orderId'],'refundType'=>3])
            ->select(DB::raw('sum(refundPrice) as refundPrice'))->get()->toArray();
        $forfeitRefund=(int)head(Arr::flatten($forfeitRefund));

        //实际付款的车损和违章 减去 已经退了的 再加上 orderPrice

        $damage=$orderInfo['damagePrice'] - $damageRefund;

        $forfeit=$orderInfo['forfeitPrice'] - $forfeitRefund;

        $refMoney=$damage + $forfeit + $orderInfo['orderPrice'];

        //根据payWay调用退款
        $payWay==='钱包' ? $this->wallet($orderInfo,$refMoney) : $this->wx($orderInfo,$refundInfo,$refMoney);

        //修改订单状态
        $order=order::where('orderId',$orderInfo['orderId'])->first();

        $order->orderStatus='已退单';

        $order->save();

        //修改退款状态
        $refund=refundInfo::where('refundId',$refundInfo['refundId'])->first();

        $refund->isFinish=1;

        $refund->save();

        return true;
    }

    //退车损
    private function damage($orderInfo,$refundInfo,$payWay)
    {
        //根据payWay调用退款
        $payWay==='钱包' ?
            $this->wallet($orderInfo,$refundInfo['refundPrice']) :
            $this->wx($orderInfo,$refundInfo,$refundInfo['refundPrice']);

        //修改退款状态
        $refund=refundInfo::where('refundId',$refundInfo['refundId'])->first();

        $refund->isFinish=1;

        $refund->save();

        return true;
    }

    //退违章
    private function forfeit($orderInfo,$refundInfo,$payWay)
    {
        //根据payWay调用退款
        $payWay==='钱包' ?
            $this->wallet($orderInfo,$refundInfo['refundPrice']) :
            $this->wx($orderInfo,$refundInfo,$refundInfo['refundPrice']);

        //修改退款状态
        $refund=refundInfo::where('refundId',$refundInfo['refundId'])->first();

        $refund->isFinish=1;

        $refund->save();

        return true;
    }
}
