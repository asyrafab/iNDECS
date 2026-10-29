<?php

use App\Http\Middleware\EnsureBangunanAccess;
use App\Http\Middleware\EnsureBangunanLegacyAccess;
use App\Http\Middleware\EnsureOscAccess;
use App\Http\Middleware\EnsurePkmAccess;
use App\Http\Middleware\EnsurePkmPermission;
use App\Http\Middleware\EnsurePublicAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'bangunan.access' => EnsureBangunanAccess::class,
            'bangunan.legacy' => EnsureBangunanLegacyAccess::class,
            'osc.access' => EnsureOscAccess::class,
            'perancang.access' => EnsurePkmAccess::class,
            'perancang.permission' => EnsurePkmPermission::class,
            'public.access' => EnsurePublicAccess::class,
        ]);
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user?->isPublicAccount()) {
                return route('portal.home');
            }

            if ($user?->canAccessBangunan()) {
                return route('bangunan.index');
            }

            if ($user?->canAccessBangunanLegacy() && ! $user->canAccessPkm()) {
                return route('bangunan.legacy.index');
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
