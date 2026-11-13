<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePublicAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            // Background requests (AMIRA chat polling) get 401 instead of a login page they would render (ADR-369).
            abort_if($request->expectsJson(), 401);

            return redirect()->route('portal.login');
        }
        abort_unless($request->user()->isPublicAccount(), 403);

        return $next($request);
    }
}
