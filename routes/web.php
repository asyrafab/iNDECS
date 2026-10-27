<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PkmApplicationController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/public.php';

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'perancang.access'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::prefix('perancang')->name('perancang.')->group(function (): void {
        Route::get('/', [PkmApplicationController::class, 'index'])->name('index');
        Route::get('/create', [PkmApplicationController::class, 'create'])
            ->middleware('perancang.permission:perancang.pkm.register')
            ->name('create');
        Route::post('/', [PkmApplicationController::class, 'store'])
            ->middleware('perancang.permission:perancang.pkm.register')
            ->name('store');
        Route::get('/{application}', [PkmApplicationController::class, 'show'])->name('show');
        Route::post('/{application}/start-review', [PkmApplicationController::class, 'startReview'])
            ->middleware('perancang.permission:perancang.pkm.review')
            ->name('start-review');
        Route::post('/{application}/documents/{documentType}', [PkmApplicationController::class, 'storeDocument'])
            ->middleware('perancang.permission:perancang.pkm.register')
            ->name('documents.store');
        Route::get('/{application}/documents/{document}', [PkmApplicationController::class, 'downloadDocument'])->name('documents.download');
        Route::post('/{application}/completeness/{item}', [PkmApplicationController::class, 'updateCompletenessItem'])
            ->middleware('perancang.permission:perancang.pkm.review')
            ->name('completeness.items.update');
        Route::post('/{application}/completeness-complete', [PkmApplicationController::class, 'completeReview'])
            ->middleware('perancang.permission:perancang.pkm.review')
            ->name('completeness.complete');
        Route::post('/{application}/completeness-return', [PkmApplicationController::class, 'returnForAmendment'])
            ->middleware('perancang.permission:perancang.pkm.review')
            ->name('completeness.return');
        Route::post('/{application}/resubmit', [PkmApplicationController::class, 'resubmit'])
            ->middleware('perancang.permission:perancang.pkm.register')
            ->name('resubmit');
    });
});
