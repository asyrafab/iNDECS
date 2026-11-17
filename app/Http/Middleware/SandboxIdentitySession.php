<?php

namespace App\Http\Middleware;

use App\Services\Identity\SandboxIdentity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SandboxIdentitySession
{
    public function handle(Request $request, Closure $next)
    {
        $pilot = $request->session()->get('sandbox_identity_auth');
        if ($pilot && $request->user()) {
            if ($pilot['user'] !== $request->user()->id) {
                $request->session()->forget('sandbox_identity_auth');
            } elseif (now()->timestamp >= $pilot['started'] + config('mydigitalid.absolute_seconds')
                || now()->timestamp >= $pilot['last'] + config('mydigitalid.idle_seconds')
                || ! app(SandboxIdentity::class)->eligible($request->user())
                || ! app(SandboxIdentity::class)->linked($request->user())) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Sesi tamat.'], 401) : redirect()->route('portal.login');
            } else {
                $request->session()->put('sandbox_identity_auth.last', now()->timestamp);
            }
        }

        $logoutUser = $pilot && $request->routeIs('portal.logout') ? $request->user() : null;
        $response = $next($request);
        if ($logoutUser && ! Auth::check()) {
            try {
                app(SandboxIdentity::class)->audit($logoutUser, 'logged_out');
            } catch (\Throwable) {
                // A failed audit sink must not prevent local session invalidation.
                logger()->warning('identity.sandbox.logout_audit_failed');
            }
        }

        return $response;
    }
}
