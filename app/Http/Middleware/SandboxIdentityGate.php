<?php

namespace App\Http\Middleware;

use App\Services\Identity\SandboxIdentity;
use Closure;
use Illuminate\Http\Request;

final class SandboxIdentityGate
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(SandboxIdentity::class)->enabled(), 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
