<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\PublicAccessController;
use App\Http\Controllers\PublicPortalController;
use Illuminate\Support\Facades\Route;

Route::view('/tentang-indecs', 'public.about')->name('about');

Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [PublicAccessController::class, 'page'])->name('login');
        Route::get('/register', fn () => app(PublicAccessController::class)->page('register'))->name('register');
        Route::get('/email', fn () => app(PublicAccessController::class)->page('email'))->name('email');
        Route::get('/forgot-password', fn () => app(PublicAccessController::class)->page('forgot'))->name('forgot');
        Route::post('/login', [PublicAccessController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
        Route::post('/register', [PublicAccessController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
        Route::get('/google', [GoogleLoginController::class, 'redirect'])->middleware('throttle:10,1')->name('google');
    });
    Route::get('/google/callback', [GoogleLoginController::class, 'callback'])->middleware('throttle:10,1')->name('google.callback');
    Route::post('/email/{purpose}', [PublicAccessController::class, 'requestLink'])->middleware('throttle:5,1')->name('email.send');
    Route::get('/link/{purpose}/{token}', [PublicAccessController::class, 'showLink'])->middleware('throttle:30,1')->name('link.show');
    Route::post('/link/{purpose}/{token}', [PublicAccessController::class, 'consumeLink'])->middleware('throttle:10,1')->name('link.consume');
    Route::middleware('public.access')->group(function (): void {
        Route::get('/', [PublicPortalController::class, 'home'])->name('home');
        Route::get('/account', [PublicAccessController::class, 'account'])->name('account');
        Route::get('/library/{document}', [PublicPortalController::class, 'download'])->whereUlid('document')->middleware('throttle:30,1')->name('library.download');
        Route::get('/drafts', [PublicPortalController::class, 'index'])->name('drafts.index');
        Route::get('/drafts/create', [PublicPortalController::class, 'create'])->name('drafts.create');
        Route::post('/drafts', [PublicPortalController::class, 'store'])->middleware('throttle:20,1')->name('drafts.store');
        Route::get('/drafts/{draft}/edit', [PublicPortalController::class, 'edit'])->whereUlid('draft')->name('drafts.edit');
        Route::put('/drafts/{draft}', [PublicPortalController::class, 'update'])->whereUlid('draft')->middleware('throttle:20,1')->name('drafts.update');
        Route::post('/account', [PublicAccessController::class, 'claim'])->middleware('throttle:5,1')->name('claim');
        Route::get('/account/google', [GoogleLoginController::class, 'redirect'])->name('google.link');
        Route::post('/logout', [PublicAccessController::class, 'logout'])->name('logout');
    });
});
