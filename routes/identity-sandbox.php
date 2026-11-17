<?php

use App\Http\Controllers\Auth\SandboxIdentityController;
use App\Http\Middleware\SandboxIdentityGate;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.identity.')->middleware([SandboxIdentityGate::class, 'throttle:60,1'])->group(function (): void {
    Route::get('/mydigitalid', [SandboxIdentityController::class, 'page'])->name('page');
    Route::post('/mydigitalid/login', [SandboxIdentityController::class, 'login'])->name('login');
    Route::get('/mydigitalid/callback', [SandboxIdentityController::class, 'callback'])->name('callback');
    Route::post('/account/mydigitalid/enrolment', [SandboxIdentityController::class, 'enrol'])->name('enrol');
    Route::post('/account/mydigitalid/enrolment/verify', [SandboxIdentityController::class, 'verifyEnrolment'])->name('enrol.verify');
    Route::post('/account/mydigitalid/link', [SandboxIdentityController::class, 'link'])->name('link');
    Route::post('/account/mydigitalid/confirm', [SandboxIdentityController::class, 'confirm'])->name('confirm');
    Route::get('/otp', [SandboxIdentityController::class, 'page'])->name('otp');
    Route::post('/otp/request', [SandboxIdentityController::class, 'requestOtp'])->name('otp.request');
    Route::post('/otp/resend', [SandboxIdentityController::class, 'requestOtp'])->name('otp.resend');
    Route::post('/otp/verify', [SandboxIdentityController::class, 'verifyOtp'])->name('otp.verify');
});
