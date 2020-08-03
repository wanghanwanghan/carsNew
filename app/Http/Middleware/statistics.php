<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Redis;

class statistics
{
    public function handle($request, Closure $next)
    {
        $today=Carbon::now()->format('Ymd');

        //访问量
        Redis::hincrby('pv',$today,1);

        //独立访客
        $ip=trim($request->getClientIp());

        if (!empty($ip))
        {
            //查看是否在集合中含有成员，含有返回1，不含有返回0
            $check=Redis::sismember($today.'uv',$ip);

            if (!$check)
            {
                //加入集合
                Redis::sadd($today.'uv',$ip);
                //uv + 1
                Redis::hincrby('uv',$today,1);
            }

            Redis::expire($today.'uv',3600);
        }

        return $next($request);
    }
}
