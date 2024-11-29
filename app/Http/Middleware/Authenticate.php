<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $request->session()->put('url.intended', $request->url());

        return redirect()->route('login')
            ->with('message', 'Please log in to access this page.')
            ->getTargetUrl();
    }
}
