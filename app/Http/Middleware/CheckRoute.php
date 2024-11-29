<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
class CheckRoute
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only clear route not found messages if the current request was successful
        if ($response->getStatusCode() === 200 && session()->has('routeNotFound')) {
            session()->forget(['routeNotFound', 'routeNotFoundMessage']);
        }
        
        return $response;
    }
}
