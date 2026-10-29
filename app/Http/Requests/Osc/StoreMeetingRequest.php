<?php

declare(strict_types=1);

namespace App\Http\Requests\Osc;

use App\Models\Osc\Meeting;
use App\Services\Osc\OscAccess;
use App\Services\Osc\OscTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OscAccess::canManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $staff = Rule::exists('users', 'id')->where('account_type', 'staff')->where('is_active', true);

        return [
            'number' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Meeting::query()->where('meeting_type', 'jawatankuasa_osc')->where('number', $value)->exists()) {
                    $fail('Nombor mesyuarat telah digunakan.');
                }
            }],
            'scheduled_at' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strtotime($value) !== false && OscTime::fromLocal($value)->lessThanOrEqualTo(now())) {
                    $fail('Tarikh mesyuarat mesti pada masa hadapan.');
                }
            }],
            'venue' => ['required', 'string', 'max:255'],
            'proposal_cutoff_at' => ['required', 'date', 'before_or_equal:scheduled_at'],
            'paper_cutoff_at' => ['required', 'date', 'before_or_equal:scheduled_at'],
            'chair_id' => ['required', 'integer', $staff],
            'secretary_id' => ['required', 'integer', $staff],
            'steno_id' => ['nullable', 'integer', $staff],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', $staff],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'proposal_cutoff_at.before_or_equal' => 'Tarikh tutup cadangan mesti pada atau sebelum tarikh mesyuarat.',
            'paper_cutoff_at.before_or_equal' => 'Tarikh tutup kertas mesti pada atau sebelum tarikh mesyuarat.',
            'chair_id.exists' => 'Pengerusi mesti kakitangan yang aktif.',
            'secretary_id.exists' => 'Setiausaha mesti kakitangan yang aktif.',
            'steno_id.exists' => 'STENO mesti kakitangan yang aktif.',
            'member_ids.*.exists' => 'Setiap ahli mesti kakitangan yang aktif.',
        ];
    }

    /**
     * Validated data with the three times converted from Malaysian local time to UTC instants.
     * Comparisons between the three fields in rules() are made on the local strings, which share one zone.
     *
     * @return array<string, mixed>
     */
    public function meetingData(): array
    {
        $data = $this->validated();
        foreach (['scheduled_at', 'proposal_cutoff_at', 'paper_cutoff_at'] as $field) {
            $data[$field] = OscTime::fromLocal((string) $data[$field]);
        }

        return $data;
    }

    public function attributes(): array
    {
        return [
            'number' => 'nombor mesyuarat', 'scheduled_at' => 'tarikh mesyuarat', 'venue' => 'tempat',
            'proposal_cutoff_at' => 'tarikh tutup cadangan', 'paper_cutoff_at' => 'tarikh tutup kertas',
            'chair_id' => 'pengerusi', 'secretary_id' => 'setiausaha', 'steno_id' => 'STENO', 'member_ids' => 'ahli',
        ];
    }
}
