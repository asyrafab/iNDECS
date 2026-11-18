<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentRequirement;
use App\Models\LandscapeApplication;
use App\Services\DocumentCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LandskapDocumentCatalogueController extends Controller
{
    public function edit(Request $request, string $type): View
    {
        $this->authorizeType($request, $type);
        $catalogue = DB::table('core.document_catalogues')->where('application_type_id', $type)->first();
        $items = DocumentRequirement::query()->with('documentType')->where('application_type_id', $type)
            ->where('is_active', true)->whereNull('permission_category')->whereNull('permission_type')->orderBy('sort_order')->get()
            ->map(fn ($item) => [
                'code' => DocumentCatalogueService::itemCode($type, $item->documentType->code),
                'name' => $item->documentType->name,
                'is_required' => $item->is_required,
                'guidance' => $item->guidance,
                'conditions' => $item->conditions ?? [],
                'precheck_keywords_text' => implode(', ', $item->precheck_keywords ?? []),
            ])->all();

        return view('landskap.document-catalogue', compact('type', 'catalogue', 'items'));
    }

    public function publish(Request $request, string $type, DocumentCatalogueService $service): RedirectResponse
    {
        $this->authorizeType($request, $type);
        $data = $request->all();
        foreach ($data['items'] ?? [] as $index => $item) {
            if (is_array($item) && array_key_exists('precheck_keywords_text', $item)) {
                $data['items'][$index]['precheck_keywords'] = array_values(array_filter(array_map('trim', explode(',', (string) $item['precheck_keywords_text']))));
                unset($data['items'][$index]['precheck_keywords_text']);
            }
        }
        $service->publish($type, $data, $request->user());

        return to_route('landskap.document-catalogue.edit', $type)->with('success', 'Versi katalog dokumen disahkan. Senarai semak permohonan sedia ada dikekalkan.');
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(in_array($type, LandscapeApplication::TYPES, true), 404);
        abort_unless($request->user()->canPerformLandskap('access') && $request->user()->canPerformLandskap('documents.configure'), 403);
    }
}
