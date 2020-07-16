<?php

namespace App\Console\Commands;

use App\Http\Models\coupon;
use App\Http\Models\order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteOrder extends Command
{
    protected $signature = 'deleteOrder';

    protected $description = '删除1小时没支付的订单';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //1小时前
        $time=Carbon::now()->subHours(1)->format('Y-m-d H:i:s');

        $orderInfo=order::where('created_at','<',$time)->where('orderStatus','待支付')->get()->toArray();

        //删除
        foreach ($orderInfo as $one)
        {
            try
            {
                $info=order::find($one['id']);

                if (is_numeric($info->coupon1) && $info->coupon1!=0)
                {
                    $coupon=coupon::find($info->coupon1);
                    $coupon->isUse=0;
                    $coupon->save();
                }

                if (is_numeric($info->coupon2) && $info->coupon2!=0)
                {
                    $coupon=coupon::find($info->coupon2);
                    $coupon->isUse=0;
                    $coupon->save();
                }

                if (is_numeric($info->coupon3) && $info->coupon3!=0)
                {
                    $coupon=coupon::find($info->coupon3);
                    $coupon->isUse=0;
                    $coupon->save();
                }

                $info->delete();

            }catch (\Exception $e)
            {
                continue;
            }
        }

        return true;
    }
}
