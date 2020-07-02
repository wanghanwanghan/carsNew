<?php

namespace App\Http\Middleware;

use Closure;

class testMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        return $response;
    }
}
