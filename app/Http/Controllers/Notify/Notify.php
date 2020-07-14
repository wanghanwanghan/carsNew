<?php

namespace App\Http\Controllers\Notify;

use App\Http\Controllers\Controller;
use App\Http\Models\order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Yansongda\LaravelPay\Facades\Pay;

class Notify extends Controller
{
    public function wxNotify(Request $request)
    {
        //{
        //    "appid":"wx4633f818cd94cd19",
        //    "bank_type":"OTHERS",
        //    "cash_fee":"1",
        //    "fee_type":"CNY",
        //    "is_subscribe":"N",
        //    "mch_id":"1600674992",
        //    "nonce_str":"2zxaY5x19NejnrPd",
        //    "openid":"ohNvz5LACwF_PjvuIEEDL5ZG3mwQ",
        //    "out_trade_no":"cf9444c8f28107f2c5d8564dbe0661ce",
        //    "result_code":"SUCCESS",
        //    "return_code":"SUCCESS",
        //    "sign":"CDA2417B30E1D6E1D09340C47D2685CC",
        //    "time_end":"20200714165424",
        //    "total_fee":"1",
        //    "trade_type":"JSAPI",
        //    "transaction_id":"4200000621202007142999356192"
        //}

        $pay=Pay::wechat();

        $data=$pay->verify();

        //拿订单信息
        $orderInfo=order::where('orderId',$data->out_trade_no)->first();

        //检查回调中的支付状态
        if ($data->result_code=='SUCCESS')
        {
            //支付成功
            $status=explode('_',$orderInfo->NotifyInfo);

            $orderInfo->payWay=head($status);
            $orderInfo->orderStatus='待确认';
            $orderInfo->NotifyInfo=json_encode($data);

        }else
        {
            //支付失败
            $orderInfo->orderStatus='支付失败';
            $orderInfo->NotifyInfo=json_encode($data);
        }

        $orderInfo->save();

        $key='MiniPay_orderId_'.$data->out_trade_no;

        Redis::set($key,json_encode($data));

        Redis::expire($key,86400 * 7);

        return $pay->success()->send();
    }

}
