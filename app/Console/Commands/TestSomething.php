<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class TestSomething extends Command
{
    protected $signature = 'TestSomething';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //1万=1.1万，3w=3.5w，5w=6w
        $res=Redis::hgetall('purchaseList');

        $res['t1']=['payMoney'=>10000,'money'=>11000];
        $res['t2']=['payMoney'=>30000,'money'=>35000];
        $res['t3']=['payMoney'=>50000,'money'=>60000];

        Redis::hset('purchaseList','t1',json_encode(['payMoney'=>10000,'money'=>11000]));
        Redis::hset('purchaseList','t2',json_encode(['payMoney'=>30000,'money'=>35000]));
        Redis::hset('purchaseList','t3',json_encode(['payMoney'=>50000,'money'=>60000]));

        return true;
    }
}
