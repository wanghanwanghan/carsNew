<?php

namespace App\Http\Middleware;

use Closure;

class testMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
