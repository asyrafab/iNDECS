<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePkmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessPkm(), 403, 'Akses modul PKM tidak dibenarkan.');

        return $next($request);
    }
}
