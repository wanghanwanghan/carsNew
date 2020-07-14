<?php

namespace App\Console\Commands;

use App\Http\Models\order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteOrder extends Command
{
    protected $signature = 'deleteOrder';

    protected $description = '删除3小时没支付的订单';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //3小时前
        $time=Carbon::now()->subHours(3)->format('Y-m-d H:i:s');

        $orderInfo=order::where('created_at','<',$time)->where('orderStatus','待支付')->get()->toArray();

        //删除
        foreach ($orderInfo as $one)
        {
            order::find($one['id'])->delete();
        }

        return true;
    }
}
