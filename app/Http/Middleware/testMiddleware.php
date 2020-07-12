<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use wanghanwanghan\someUtils\control;

class testMiddleware
{
    //验证token中间键
    public function handle($request, Closure $next)
    {
        //登录验证
        $Authorization=trim($request->header('Authorization'));

        $salt=env('AES_SALT','');

        $decode=control::aesDecode($Authorization,$salt);

        if ($decode === false)
        {
            return response()->json(['code'=>201,'result'=>[],'msg'=>'token错误.']);
        }

        $decode=explode('_',$decode);

        if (!is_numeric(head($decode)) || strlen(head($decode)) !== 11)
        {
            return response()->json(['code'=>201,'result'=>[],'msg'=>'token错误..']);
        }

        $phone=head($decode);

        if ($phone != $request->phone)
        {
            return response()->json(['code'=>201,'result'=>[],'msg'=>'手机号码验证错误...']);
        }

        $redisData=Redis::hget('auth',$phone);

        if (empty($redisData))
        {
            return response()->json(['code'=>201,'result'=>[],'msg'=>'未登录....']);
        }

        if ($Authorization !== $redisData)
        {
            return response()->json(['code'=>201,'result'=>[],'msg'=>'token验证失败.....']);
        }

        return $next($request);
    }
}
