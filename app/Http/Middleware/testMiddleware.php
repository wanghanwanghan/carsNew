<?php

namespace App\Http\Middleware;

use Closure;

class testMiddleware
{
    public function handle($request, Closure $next)
    {
        //登录验证
        $Authorization=$request->header('Authorization');











        return $next($request);
    }
}
