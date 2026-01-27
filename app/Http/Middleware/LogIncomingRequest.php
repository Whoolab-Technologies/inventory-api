<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogIncomingRequest
{
    public function handle(Request $request, Closure $next)
    {
        $method = $request->method();
        $url = $request->fullUrl();
        $data = in_array($method, ['POST', 'PUT', 'PATCH'])
            ? $request->all()
            : $request->query();

        Log::info('Incoming Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data
        ]);

        return $next($request);
    }
}