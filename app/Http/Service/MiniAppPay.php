<?php

namespace App\Http\Service;

use wanghanwanghan\someUtils\traits\Singleton;
use Yansongda\LaravelPay\Facades\Pay;

class MiniAppPay
{
    use Singleton;

    protected $appid;
    protected $secret;
    protected $grant_type;

    public function __construct()
    {
        $this->appid=env('WECHAT_MINIAPP_ID','');
        $this->secret=env('WECHAT_OPENID_KEY','');
        $this->grant_type='authorization_code';
    }

    //获取用户openid
    private function getOpenId($code)
    {
        $url='https://api.weixin.qq.com/sns/jscode2session?appid=';
        $url.=$this->appid;
        $url.='&secret=';
        $url.=$this->secret;
        $url.='&js_code=';
        $url.=$code;
        $url.='&grant_type=';
        $url.=$this->grant_type;

        $data=file_get_contents($url);

        return json_decode($data,true);
    }

    //创建订单
    public function createMiniAppOrder($code,$orderId='',$body='1分钱测试',$money=0.01)
    {
        $openId=$this->getOpenId($code);

        $order = [
            'out_trade_no'=>$orderId,
            'body'=>$body,
            'total_fee'=>$money * 100,
            'openid'=>last($openId),
        ];

        return Pay::wechat()->miniapp($order);
    }
}
