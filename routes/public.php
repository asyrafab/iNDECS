<?php

use App\Http\Controllers\AmiraChatController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\PublicAccessController;
use App\Http\Controllers\PortalCheckPaymentController;
use App\Http\Controllers\PortalCheckResultController;
use App\Http\Controllers\PortalDraftDocumentController;
use App\Http\Controllers\PortalGuidanceController;
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
        Route::get('/amira/history', [PortalGuidanceController::class, 'index'])->middleware('throttle:60,1')->name('amira.history');
        Route::post('/amira/messages', [PortalGuidanceController::class, 'store'])->middleware('throttle:30,1')->name('amira.messages');
        Route::get('/', [PublicPortalController::class, 'home'])->name('home');
        Route::get('/account', [PublicAccessController::class, 'account'])->name('account');
        Route::get('/library/{document}', [PublicPortalController::class, 'download'])->whereUlid('document')->middleware('throttle:30,1')->name('library.download');
        Route::get('/drafts', [PublicPortalController::class, 'index'])->name('drafts.index');
        Route::get('/drafts/create', [PublicPortalController::class, 'create'])->name('drafts.create');
        Route::post('/drafts', [PublicPortalController::class, 'store'])->middleware('throttle:20,1')->name('drafts.store');
        Route::get('/drafts/{draft}/edit', [PublicPortalController::class, 'edit'])->whereUlid('draft')->name('drafts.edit');
        // ADR-363: owner-only uploads and free pre-check on a draft.
        // Upload-first drafts: drop files, a folder or one ZIP; AIRS's free local check sorts them, the rest wait in the tray.
        Route::post('/drafts/drop', [PortalDraftDocumentController::class, 'drop'])->middleware('throttle:20,1')->name('drafts.drop');
        Route::post('/drafts/{draft}/documents', [PortalDraftDocumentController::class, 'add'])->whereUlid('draft')->middleware('throttle:20,1')->name('drafts.documents.add');
        Route::post('/drafts/{draft}/documents/{document}/assign', [PortalDraftDocumentController::class, 'assign'])->whereUlid(['draft', 'document'])->middleware('throttle:30,1')->name('drafts.documents.assign');
        Route::delete('/drafts/{draft}/documents/{document}', [PortalDraftDocumentController::class, 'remove'])->whereUlid(['draft', 'document'])->middleware('throttle:30,1')->name('drafts.documents.remove');
        Route::post('/drafts/{draft}/documents/{code}', [PortalDraftDocumentController::class, 'store'])->whereUlid('draft')->where('code', '[a-z][a-z0-9_]{0,35}')->middleware('throttle:20,1')->name('drafts.documents.store');
        Route::post('/drafts/{draft}/documents/{document}/confirm', [PortalDraftDocumentController::class, 'confirm'])->whereUlid(['draft', 'document'])->middleware('throttle:30,1')->name('drafts.documents.confirm');
        Route::get('/drafts/{draft}/documents/{document}', [PortalDraftDocumentController::class, 'download'])->whereUlid(['draft', 'document'])->name('drafts.documents.download');
        Route::get('/drafts/{draft}/checklist', [PortalDraftDocumentController::class, 'checklist'])->whereUlid('draft')->middleware('throttle:60,1')->name('drafts.checklist');
        // ADR-369: chat with AMIRA on the home page and on a draft. Answers are queued; the panel polls the thread.
        Route::post('/amira/consent', [AmiraChatController::class, 'consent'])->middleware('throttle:10,1')->name('amira.consent');
        Route::get('/amira/thread', [AmiraChatController::class, 'thread'])->middleware('throttle:60,1')->name('amira.thread');
        Route::post('/amira', [AmiraChatController::class, 'ask'])->middleware('throttle:10,1')->name('amira.ask');
        Route::get('/drafts/{draft}/amira/thread', [AmiraChatController::class, 'thread'])->whereUlid('draft')->middleware('throttle:60,1')->name('drafts.amira.thread');
        Route::post('/drafts/{draft}/amira', [AmiraChatController::class, 'ask'])->whereUlid('draft')->middleware('throttle:10,1')->name('drafts.amira.ask');
        // Paid AIRS check: fee payment (staging gateway only until the MBSP gateway is integrated).
        Route::post('/drafts/{draft}/payment', [PortalCheckPaymentController::class, 'start'])->whereUlid('draft')->middleware('throttle:10,1')->name('drafts.payment.start');
        Route::get('/payments/{payment}/staging', [PortalCheckPaymentController::class, 'staging'])->whereUlid('payment')->name('payments.staging');
        Route::post('/payments/{payment}/staging/confirm', [PortalCheckPaymentController::class, 'confirmStaging'])->whereUlid('payment')->middleware('throttle:10,1')->name('payments.staging.confirm');
        // Applicant-safe AIRS check results and the applicant's own evidence documents.
        Route::get('/drafts/{draft}/check', [PortalCheckResultController::class, 'show'])->whereUlid('draft')->middleware('throttle:60,1')->name('drafts.check');
        Route::get('/drafts/{draft}/check/documents/{document}', [PortalCheckResultController::class, 'document'])->whereUlid(['draft', 'document'])->middleware('throttle:60,1')->name('drafts.check.document');
        Route::post('/drafts/{draft}/check/extend', [PortalCheckResultController::class, 'extend'])->whereUlid('draft')->middleware('throttle:10,1')->name('drafts.check.extend');
        Route::post('/drafts/{draft}/check/amend', [PortalCheckResultController::class, 'amend'])->whereUlid('draft')->middleware('throttle:10,1')->name('drafts.check.amend');
        Route::post('/drafts/{draft}/check/submit', [PortalCheckResultController::class, 'submit'])->whereUlid('draft')->middleware('throttle:10,1')->name('drafts.check.submit');
        Route::put('/drafts/{draft}', [PublicPortalController::class, 'update'])->whereUlid('draft')->middleware('throttle:20,1')->name('drafts.update');
        Route::delete('/drafts/{draft}', [PublicPortalController::class, 'destroy'])->whereUlid('draft')->middleware('throttle:20,1')->name('drafts.destroy');
        Route::post('/account', [PublicAccessController::class, 'claim'])->middleware('throttle:5,1')->name('claim');
        Route::get('/account/google', [GoogleLoginController::class, 'redirect'])->name('google.link');
        Route::post('/logout', [PublicAccessController::class, 'logout'])->name('logout');
    });
});
