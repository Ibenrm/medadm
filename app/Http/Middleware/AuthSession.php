<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('email')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
