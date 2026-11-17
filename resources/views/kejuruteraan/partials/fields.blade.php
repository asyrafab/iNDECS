{{-- MATRIX-extracted profile fields and officer dispositions. Official application particulars are never changed here. --}}
@if ($check['fields'] !== [])
@php($canReview = $view['journey']?->state === \App\Enums\PaidCheckState::HandedOver && $view['final_check_number'] === $check['number'] && auth()->user()->canPerformLjup('review'))
<div id="profil">
    <h3>Profil permohonan (diekstrak oleh MATRIX)</h3>
    <div class="table-wrap technical-profile-table" role="region" aria-label="Medan profil dan semakan pegawai" tabindex="0">
    <table class="data-table">
        <thead><tr><th>Medan</th><th>Nilai diekstrak</th><th>Semakan pegawai</th>@if ($canReview)<th>Tindakan</th>@endif</tr></thead>
        <tbody>
        @foreach ($check['fields'] as $field)
            <tr>
                <td>{{ $field['field'] }}</td>
                <td>{{ $field['extracted'] ?? '—' }}@if ($field['page']) <small>(halaman {{ $field['page'] }})</small>@endif</td>
                <td>@if ($field['disposition']){{ $field['disposition'] }}: {{ $field['value'] ?? '—' }} <small>· {{ $field['officer'] }} · {{ $field['reason'] }}</small>@else — @endif</td>
                @if ($canReview)
                <td>
                    <form method="POST" action="{{ route('kejuruteraan.fields.review', $application) }}">
                        @csrf
                        <input type="hidden" name="review_version_id" value="{{ $check['version']->id }}">
                        <input type="hidden" name="field" value="{{ $field['field'] }}">
                        <input type="hidden" name="expected_revision" value="{{ $field['revision'] }}">
                        <select name="disposition"><option value="ACCEPTED">Sahkan</option><option value="CORRECTED">Betulkan</option><option value="REJECTED">Tolak</option></select>
                        <input name="value" maxlength="2000" placeholder="Nilai betul">
                        <input name="reason" maxlength="2000" required placeholder="Sebab">
                        <button class="button button-secondary button-small" type="submit">Simpan</button>
                    </form>
                </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
