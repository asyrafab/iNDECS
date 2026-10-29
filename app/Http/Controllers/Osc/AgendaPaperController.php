<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Http\Controllers\Controller;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\AgendaItemPaper;
use App\Services\Osc\AgendaPaperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgendaPaperController extends Controller
{
    public function store(Request $request, AgendaItem $item, AgendaPaperService $papers): RedirectResponse
    {
        $request->validate(['paper' => ['required', 'file']], ['paper.required' => 'Pilih fail PDF kertas kerja.']);
        $papers->upload($item, $request->file('paper'), $request->user());

        return back()->with('status', 'Kertas kerja telah dimuat naik.');
    }

    public function show(Request $request, AgendaItemPaper $paper, AgendaPaperService $papers): StreamedResponse
    {
        $response = $papers->download($paper, $request->user());
        $name = $paper->original_name;
        $fallback = preg_replace('/[^\x20-\x7e]|[%\\\\"]/', '_', $name) ?: 'kertas.pdf';
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $fallback));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
