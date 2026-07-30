<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ParentAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('parent_authed')) {
            return redirect()->route('parent.dashboard');
        }

        return view('parent.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string']]);

        $key = 'parent-pin:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => 'Too many tries. Wait a minute and try again.']);
        }

        if (! hash_equals((string) config('timer.parent_pin'), $data['pin'])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['pin' => 'Wrong parent PIN.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('parent_authed', true);

        return redirect()->route('parent.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('parent_authed');

        return redirect()->route('parent.login');
    }
}
