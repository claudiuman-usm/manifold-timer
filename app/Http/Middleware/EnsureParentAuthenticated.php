<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParentAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('parent_authed')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Parent PIN required.'], 401)
                : redirect('/');
        }

        return $next($request);
    }
}
