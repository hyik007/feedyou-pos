<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class StaffSessionController extends Controller
{
    public function create(): View
    {
        return view('staff.login');
    }

    public function store(Request $request, SettingsStore $settings): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string']]);
        $key = 'staff-pin:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['pin' => 'Too many attempts. Try again in a minute.']);
        }

        $hash = $settings->staffPinHash();
        if (! $hash || Hash::check($request->input('pin'), $hash)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            $request->session()->put('staff_ok', true);

            return redirect()->intended(route('pos'));
        }

        RateLimiter::hit($key, 60);

        return back()->withErrors(['pin' => 'Wrong PIN.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('staff_ok');

        return redirect('/');
    }
}
