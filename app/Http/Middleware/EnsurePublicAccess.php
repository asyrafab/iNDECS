<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePublicAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return redirect()->route('portal.login');
        }
        abort_unless($request->user()->isPublicAccount(), 403);

        return $next($request);
    }
}
