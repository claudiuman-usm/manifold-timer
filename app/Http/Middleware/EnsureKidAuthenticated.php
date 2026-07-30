<?php

namespace App\Http\Middleware;

use App\Models\Kid;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKidAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $kidId = $request->session()->get('kid_id');
        $kid = $kidId ? Kid::find($kidId) : null;

        if (! $kid) {
            $request->session()->forget('kid_id');

            return $request->expectsJson()
                ? response()->json(['message' => 'Not signed in.'], 401)
                : redirect('/');
        }

        // Make the current kid available to controllers & views.
        $request->attributes->set('kid', $kid);
        view()->share('currentKid', $kid);

        return $next($request);
    }
}
