<?php

namespace App\Http\Service;

use wanghanwanghan\someUtils\traits\Singleton;

class GetOpenId
{
    use Singleton;

    protected $appid;
    protected $secret;
    protected $grant_type;

    public function __construct()
    {
        $this->appid=env('WECHAT_MINIAPP_ID','');
        $this->secret=env('WECHAT_KEY','');
        $this->grant_type='authorization_code';
    }

    //获取用户openid
    public function getOpenidAction($code)
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

}
