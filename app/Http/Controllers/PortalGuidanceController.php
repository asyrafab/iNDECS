<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AmiraGuidanceTurn;
use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\Portal\PortalBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PortalGuidanceController extends Controller
{
    public function __construct(private readonly PortalBoard $board) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['draft_id' => ['nullable', 'ulid'], 'before' => ['nullable', 'ulid']]);
        [$context] = $this->context($request, $data['draft_id'] ?? null);
        $turns = AmiraGuidanceTurn::query()->where('user_id', $request->user()->id)->where('context', $context)
            ->when($data['before'] ?? null, fn ($query, $before) => $query->where('id', '<', $before))
            ->orderByDesc('id')->limit(31)->get();
        $more = $turns->count() > 30;
        $page = $turns->take(30)->reverse()->values();

        return response()->json(['turns' => $page->map(fn ($turn) => $this->present($turn)), 'before' => $more ? $page->first()->id : null])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'draft_id' => ['nullable', 'ulid'],
            'kind' => ['required', Rule::in(['opening', 'page', 'fields', 'next', 'question'])],
            'request_id' => ['required', 'uuid'],
            'question' => ['required_unless:kind,opening', 'nullable', 'string', 'max:300'],
        ]);
        [$context, $draft] = $this->context($request, $data['draft_id'] ?? null);
        $user = $request->user();
        $turns = DB::transaction(function () use ($user, $context, $draft, $data) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $base = AmiraGuidanceTurn::query()->where('user_id', $user->id)->where('context', $context);
            if ($data['kind'] === 'opening') {
                $board = $this->board->for($user, focus: $draft);
                $opening = $base->clone()->firstOrCreate(['request_id' => 'opening'], [
                    'user_id' => $user->id, 'context' => $context, 'heading' => '', 'reply' => $board['greeting'],
                ]);
                $suggestion = $base->clone()->firstOrCreate(['request_id' => 'suggestions'], [
                    'user_id' => $user->id, 'context' => $context, 'heading' => '',
                    'reply' => ($board['todos'] === [] ? 'Tiada tindakan diperlukan hari ini. ' : 'Perkara untuk tindakan anda: '.implode('; ', array_column($board['todos'], 'text')).'. ')
                        .'Apa yang ingin anda lakukan hari ini? Saya boleh menerangkan halaman ini, membantu anda memahami borang atau menunjukkan langkah seterusnya.',
                ]);

                return collect([$opening, $suggestion]);
            }
            $question = trim($data['question']);
            abort_if($question === '', 422, 'Tulis soalan anda dahulu.');
            $existing = $base->clone()->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_if($existing->question !== $question, 409, 'Permintaan ini telah digunakan untuk soalan lain.');

                return collect([$existing]);
            }
            [$heading, $reply] = $this->guidance($data['kind'], $question, $user, $draft);

            return collect([AmiraGuidanceTurn::query()->create([
                'user_id' => $user->id, 'context' => $context, 'request_id' => $data['request_id'],
                'question' => $question, 'heading' => $heading, 'reply' => $reply,
            ])]);
        });

        return response()->json(['turns' => $turns->map(fn ($turn) => $this->present($turn))->values()])
            ->header('Cache-Control', 'no-store');
    }

    private function context(Request $request, ?string $draftId): array
    {
        $draft = $draftId ? ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draftId) : null;

        return [$draft ? 'draft:'.$draft->id : 'home', $draft];
    }

    private function guidance(string $kind, string $question, User $user, ?ApplicantDraft $draft): array
    {
        if ($kind === 'question') {
            $kind = match (true) {
                (bool) preg_match('/borang|medan|isi/iu', $question) => 'fields',
                (bool) preg_match('/seterusnya|teruskan|tindakan|peringkat/iu', $question) => 'next',
                (bool) preg_match('/halaman|dokumen|panduan/iu', $question) => 'page',
                default => 'unavailable',
            };
        }

        return match ($kind) {
            'fields' => ['Medan yang tersedia', $draft
                ? 'Semak tajuk, jenis permohonan, maklumat pemohon, lokasi tapak dan butiran projek pada borang draf. Lengkapkan medan wajib dan semak dokumen yang diminta. Saya tidak mengisi atau menghantar borang bagi pihak anda.'
                : 'Pilih Permohonan baharu untuk membuka borang draf, atau sambung draf dalam Fail saya. Saya boleh menerangkan medan yang tersedia apabila anda membuka draf.'],
            'next' => ['Langkah seterusnya', $this->board->for($user, focus: $draft)['todos'][0]['text'] ?? ($draft
                ? 'Semak maklumat serta dokumen draf dan simpan perubahan anda. Draf belum dihantar kepada MBSP; simpanan draf bukan penghantaran rasmi.'
                : 'Pilih Permohonan baharu untuk menyediakan draf atau sambung draf sedia ada dalam Fail saya.')],
            'page' => ['Panduan halaman ini', $draft
                ? 'Halaman ini menyediakan draf maklumat permohonan anda. Lengkapkan maklumat, semak senarai dokumen dan simpan draf. Semakan awal ialah panduan penyediaan; ia bukan penilaian teknikal atau keputusan MBSP.'
                : 'Fail saya menyenaraikan draf milik anda dan perkara yang perlu tindakan. Pilih Permohonan baharu untuk menyediakan draf atau buka draf sedia ada. Draf belum dihantar kepada MBSP.'],
            default => ['Panduan sistem setempat', 'Perbualan AI AMIRA belum tersedia. Pilih panduan halaman, bantuan borang atau langkah seterusnya untuk panduan berdasarkan halaman ini. Jawapan AI langsung belum disambungkan.'],
        };
    }

    private function present(AmiraGuidanceTurn $turn): array
    {
        return $turn->only(['id', 'question', 'heading', 'reply', 'created_at']);
    }
}
