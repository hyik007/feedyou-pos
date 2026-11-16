<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight staff guard for a single-outlet hackathon build.
 *  - Web pages: session flag set after PIN login.
 *  - API calls: X-Staff-Token header (an HMAC handed to staff pages after login).
 * If no PIN has been configured yet, staff areas are open (first-run / dev mode).
 */
class StaffAuth
{
    public function __construct(private SettingsStore $settings) {}

    public static function tokenFor(string $pinHash): string
    {
        return hash_hmac('sha256', 'staff:'.$pinHash, (string) config('app.key'));
    }

    /** Non-throwing check, usable from controllers that serve both public and staff callers. */
    public function check(Request $request): bool
    {
        $hash = $this->settings->staffPinHash();
        if (! $hash) {
            return true;
        }
        if ($request->hasSession() && $request->session()->get('staff_ok') === true) {
            return true;
        }
        $token = $request->header('X-Staff-Token');

        return is_string($token) && hash_equals(self::tokenFor($hash), $token);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->check($request)) {
            return $next($request);
        }

        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => 'Staff login required.'], 401)
            : redirect()->guest(route('staff.login'));
    }
}
