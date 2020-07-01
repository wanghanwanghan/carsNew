<?php

namespace App\Http\Service;

use Qiniu\Auth;
use Qiniu\Sms\Sms;
use wanghanwanghan\someUtils\traits\Singleton;

class SendSms
{
    use Singleton;

    private $QiNiu_AK='TmTB5P36WqA5SxLsAgR1svRBfjNFtnIjGU0skHyk';
    private $QiNiu_SK='zo5nhyEv3JiX7bvXjEUOlWEobHXM1hAFnEKSOt6A';
    private $tempId_1='1278174705529925632';//验证码模版id

    public function send($type=['vCode',666666],$mobiles=[],$company='qiniu')
    {
        $res=false;

        switch ($company)
        {
            case 'qiniu':

                $res=$this->QiNiu($type,$mobiles);

                break;

            default:
        }

        return $res;
    }

    private function QiNiu($type,$mobiles)
    {
        $auth=new Auth($this->QiNiu_AK,$this->QiNiu_SK);

        $client=new Sms($auth);

        $resp=false;

        switch (head($type))
        {
            case 'vCode':

                //发送验证码

                try
                {
                    $resp=$client->sendMessage($this->tempId_1,$mobiles,['code'=>last($type)]);

                }catch (\Exception $e)
                {
                    $resp=false;
                }

                break;

            default:
        }

        return $resp;
    }
}
