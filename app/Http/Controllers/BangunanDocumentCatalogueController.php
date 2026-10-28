<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BangunanApplication;
use App\Models\DocumentRequirement;
use App\Services\BangunanDocumentCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BangunanDocumentCatalogueController extends Controller
{
    public function edit(Request $request, string $type): View
    {
        $this->authorizeType($request, $type);
        $catalogue = DB::table('core.document_catalogues')->where('application_type_id', $type)->first();
        $items = DocumentRequirement::query()->with('documentType')->where('application_type_id', $type)
            ->where('is_active', true)->whereNull('permission_category')->whereNull('permission_type')->orderBy('sort_order')->get()
            ->map(fn ($item) => ['code' => preg_replace('/^bina_'.strtolower($type).'_/', '', $item->documentType->code),
                'name' => $item->documentType->name, 'is_required' => $item->is_required])->all();

        return view('bangunan.document-catalogue', compact('type', 'catalogue', 'items'));
    }

    public function publish(Request $request, string $type, BangunanDocumentCatalogueService $service): RedirectResponse
    {
        $this->authorizeType($request, $type);
        $service->publish($type, $request->all(), $request->user());

        return to_route('bangunan.document-catalogue.edit', $type)->with('success', 'Versi katalog dokumen disahkan. Senarai semak permohonan sedia ada dikekalkan.');
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(in_array($type, BangunanApplication::TYPES, true), 404);
        abort_unless($request->user()->canPerformBangunan('access', $type) && $request->user()->canPerformBangunan('documents.configure', $type), 403);
    }
}
