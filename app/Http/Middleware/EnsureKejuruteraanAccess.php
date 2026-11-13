<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKejuruteraanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessKejuruteraan(), 403, 'Akses modul Kejuruteraan tidak dibenarkan.');

        return $next($request);
    }
}
