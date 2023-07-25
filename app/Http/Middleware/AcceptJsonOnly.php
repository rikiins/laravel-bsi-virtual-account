<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function PHPUnit\Framework\returnSelf;

class AcceptJsonOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('post')) return $next($request);
        
        $acceptHeader = $request->header('Content-Type');
        if ($acceptHeader != 'application/json')
        {
            return response()->json([
                'rc' => 'ERR-PARSING-MESSAGE',
                'message' => 'Invalid message format'
            ], 406);
        }

        return $next($request);
    }
}
