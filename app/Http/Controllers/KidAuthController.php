<?php

namespace App\Http\Controllers;

use App\Models\Kid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class KidAuthController extends Controller
{
    /**
     * Single entry point: one PIN pad. Nothing about the app (not even the kids'
     * names) is shown until a valid PIN is entered — so the app is not publicly
     * browsable just by knowing the URL.
     */
    public function gate(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('parent_authed')) {
            return redirect()->route('parent.dashboard');
        }
        if ($request->session()->get('kid_id') && Kid::find($request->session()->get('kid_id'))) {
            return redirect()->route('kid.show');
        }

        return view('gate');
    }

    /**
     * Resolve the entered PIN to the parent or a kid and sign them in.
     * The PIN alone identifies who you are — no name is picked first.
     */
    public function enter(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string']]);

        $key = 'gate:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withErrors(['pin' => 'Too many tries. Wait a minute and try again.']);
        }

        // Parent PIN takes precedence.
        if (hash_equals((string) config('timer.parent_pin'), $data['pin'])) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            $request->session()->put('parent_authed', true);

            return redirect()->route('parent.dashboard');
        }

        // Otherwise match a kid by PIN.
        foreach (Kid::all() as $kid) {
            if (Hash::check($data['pin'], $kid->pin)) {
                RateLimiter::clear($key);
                $request->session()->regenerate();
                $request->session()->put('kid_id', $kid->id);

                return redirect()->route('kid.show');
            }
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['pin' => 'PIN not recognised.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('kid_id');

        return redirect('/');
    }
}
