<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BangunanApplicationController;
use App\Http\Controllers\BangunanDocumentCatalogueController;
use App\Http\Controllers\BangunanDocumentController;
use App\Http\Controllers\BangunanLegacyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gis\BoundaryController;
use App\Http\Controllers\Gis\TileController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\Osc\AgendaItemController;
use App\Http\Controllers\Osc\AgendaPaperController;
use App\Http\Controllers\Osc\GisRefreshController;
use App\Http\Controllers\Osc\LayoutImageController;
use App\Http\Controllers\Osc\MeetingController;
use App\Http\Controllers\Osc\MeetingMapController;
use App\Http\Controllers\PkmApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require __DIR__.'/public.php';

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return view('welcome');
    }

    if ($request->user()->isPublicAccount()) {
        return to_route('portal.home');
    }
    if ($request->user()->canAccessBangunan()) {
        return to_route('bangunan.index');
    }
    if ($request->user()->canAccessBangunanLegacy() && ! $request->user()->canAccessPkm()) {
        return to_route('bangunan.legacy.index');
    }
    if (! $request->user()->canAccessPkm() && $request->user()->canAccessOsc()) {
        return to_route('osc.meetings.index');
    }
    abort_unless($request->user()->canAccessPkm(), 403);

    return app(DashboardController::class)($request);
})->name('dashboard');

Route::middleware(['auth', 'perancang.access'])->group(function (): void {

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

// ADR-362: department reference library. Every staff role reads; a Penolong Pengarah changes own department.
Route::middleware('auth')->prefix('library')->name('library.')->group(function (): void {
    Route::get('/', [LibraryController::class, 'index'])->name('index');
    Route::get('/create', [LibraryController::class, 'create'])->name('create');
    Route::post('/', [LibraryController::class, 'store'])->middleware('throttle:20,1')->name('store');
    Route::get('/{document}', [LibraryController::class, 'show'])->whereUlid('document')->name('show');
    Route::post('/{document}/versions', [LibraryController::class, 'storeVersion'])->whereUlid('document')->middleware('throttle:20,1')->name('versions.store');
    Route::post('/{document}/retire', [LibraryController::class, 'retire'])->whereUlid('document')->name('retire');
    Route::get('/{document}/versions/{version}', [LibraryController::class, 'download'])->whereUlid('document')->whereNumber('version')->name('download');
});

Route::middleware(['auth', 'bangunan.access'])->prefix('bangunan')->name('bangunan.')->group(function (): void {
    Route::get('/', [BangunanApplicationController::class, 'index'])->name('index');
    Route::get('/create', [BangunanApplicationController::class, 'create'])->name('create');
    Route::post('/', [BangunanApplicationController::class, 'store'])->name('store');
    Route::get('/applications/{application}', [BangunanApplicationController::class, 'show'])->name('show');
    Route::post('/applications/{application}/transition', [BangunanApplicationController::class, 'transition'])->name('transition');
    Route::get('/document-catalogue/{type}', [BangunanDocumentCatalogueController::class, 'edit'])->name('document-catalogue.edit');
    Route::post('/document-catalogue/{type}', [BangunanDocumentCatalogueController::class, 'publish'])->name('document-catalogue.publish');
    Route::get('/applications/{application}/documents', [BangunanDocumentController::class, 'index'])->name('documents.index');
    Route::post('/applications/{application}/documents/initialise', [BangunanDocumentController::class, 'initialise'])->name('documents.initialise');
    Route::post('/applications/{application}/documents/items/{item}', [BangunanDocumentController::class, 'store'])->name('documents.store');
    Route::post('/applications/{application}/documents/items/{item}/verify', [BangunanDocumentController::class, 'verify'])->name('documents.verify');
    Route::get('/applications/{application}/documents/files/{document}', [BangunanDocumentController::class, 'download'])->name('documents.download');
});

Route::middleware(['auth', 'bangunan.legacy'])->prefix('bangunan')->name('bangunan.')->group(function (): void {
    Route::get('/legacy', [BangunanLegacyController::class, 'index'])->name('legacy.index');
    Route::get('/legacy/{legacyPermohonan}', [BangunanLegacyController::class, 'show'])->name('legacy.show');
});

Route::middleware(['auth', 'osc.access'])->prefix('osc')->name('osc.')->group(function (): void {
    Route::get('/', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('/meetings/{meeting}/map', [MeetingMapController::class, 'show'])->name('meetings.map');
    Route::get('/meetings/{meeting}/map-data', [MeetingMapController::class, 'data'])->name('meetings.map-data');
    Route::post('/meetings/{meeting}/reorder', [MeetingController::class, 'reorder'])->name('meetings.reorder');
    Route::post('/meetings/{meeting}/publish', [MeetingController::class, 'publish'])->name('meetings.publish');
    Route::get('/meetings/{meeting}/items/create', [AgendaItemController::class, 'create'])->name('items.create');
    Route::post('/meetings/{meeting}/items', [AgendaItemController::class, 'store'])->name('items.store');
    Route::post('/items/{item}/endorse', [AgendaItemController::class, 'endorse'])->name('items.endorse');
    Route::post('/items/{item}/accept', [AgendaItemController::class, 'accept'])->name('items.accept');
    Route::post('/items/{item}/return', [AgendaItemController::class, 'return'])->name('items.return');
    Route::post('/items/{item}/papers', [AgendaPaperController::class, 'store'])->name('papers.store');
    Route::get('/papers/{paper}', [AgendaPaperController::class, 'show'])->name('papers.show');
    Route::get('/items/{item}/layout', [LayoutImageController::class, 'edit'])->name('layout.edit');
    Route::post('/items/{item}/layout', [LayoutImageController::class, 'store'])->name('layout.store');
    Route::post('/gis/refresh', [GisRefreshController::class, 'store'])->name('gis.refresh');
    Route::get('/layouts/{layoutImage}', [LayoutImageController::class, 'show'])->name('layout.show');
});

Route::middleware('auth')->post('/gis/applications/{application}/boundary', [BoundaryController::class, 'store'])
    ->name('gis.applications.boundary');

// Tiles exist only from the client minimum zoom (12): a low-zoom tile would aggregate the whole cadastre in one query.
Route::middleware(['auth', 'throttle:600,1'])->get('/gis/tiles/{layer}/{z}/{x}/{y}.pbf', [TileController::class, 'show'])
    ->whereIn('z', array_map('strval', range(TileController::MIN_ZOOM, TileController::MAX_ZOOM)))
    ->where(['x' => '[0-9]{1,7}', 'y' => '[0-9]{1,7}', 'layer' => '[a-z0-9_]+'])
    ->name('gis.tiles');
