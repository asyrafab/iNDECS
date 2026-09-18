<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PkmApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'pkm.access'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::prefix('pkm')->name('pkm.')->group(function (): void {
        Route::get('/', [PkmApplicationController::class, 'index'])->name('index');
        Route::get('/create', [PkmApplicationController::class, 'create'])->name('create');
        Route::post('/', [PkmApplicationController::class, 'store'])->name('store');
        Route::get('/{application}', [PkmApplicationController::class, 'show'])->name('show');
        Route::post('/{application}/start-review', [PkmApplicationController::class, 'startReview'])->name('start-review');
        Route::post('/{application}/documents/{documentType}', [PkmApplicationController::class, 'storeDocument'])->name('documents.store');
        Route::get('/{application}/documents/{document}', [PkmApplicationController::class, 'downloadDocument'])->name('documents.download');
        Route::post('/{application}/completeness/{item}', [PkmApplicationController::class, 'updateCompletenessItem'])->name('completeness.items.update');
        Route::post('/{application}/completeness-complete', [PkmApplicationController::class, 'completeReview'])->name('completeness.complete');
        Route::post('/{application}/completeness-return', [PkmApplicationController::class, 'returnForAmendment'])->name('completeness.return');
        Route::post('/{application}/resubmit', [PkmApplicationController::class, 'resubmit'])->name('resubmit');
    });
});
