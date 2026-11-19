<?php

use App\Http\Controllers\OfficerTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('tugasan')->name('tasks.')->group(function (): void {
    Route::get('/', [OfficerTaskController::class, 'index'])->name('index');
    Route::get('/baharu', [OfficerTaskController::class, 'create'])->name('create');
    Route::post('/', [OfficerTaskController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/{task}', [OfficerTaskController::class, 'show'])->whereUlid('task')->name('show');
    Route::post('/{task}/tindakan', [OfficerTaskController::class, 'act'])->whereUlid('task')->middleware('throttle:30,1')->name('act');
    Route::get('/{task}/bukti/{action}/{document}', [OfficerTaskController::class, 'evidence'])->whereUlid(['task', 'action', 'document'])->name('evidence');
});
