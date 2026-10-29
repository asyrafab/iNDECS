<?php

declare(strict_types=1);

namespace App\Http\Requests\Osc;

use App\Enums\Osc\TablingPurpose;
use App\Services\Osc\OscAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return OscAccess::canManage($user) || $user->canPerformOsc('osc.agenda.propose');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $linked = filled($this->input('application_id'));

        return [
            'purpose' => ['required', Rule::enum(TablingPurpose::class)],
            'application_id' => ['nullable', 'string', 'size:26', Rule::exists('applications', 'id')],
            'presenter_id' => ['required', 'integer', Rule::exists('users', 'id')->where('account_type', 'staff')->where('is_active', true)],
            'standalone_type' => [$linked ? 'nullable' : 'required', 'string', 'max:100'],
            'title' => [$linked ? 'nullable' : 'required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'string', 'max:20', Rule::exists('departments', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['presenter_id.exists' => 'Penyampai mesti kakitangan yang aktif.'];
    }

    public function attributes(): array
    {
        return [
            'purpose' => 'tujuan pembentangan', 'standalone_type' => 'jenis perkara', 'title' => 'tajuk', 'reference' => 'rujukan',
            'presenter_id' => 'penyampai', 'department_id' => 'jabatan',
        ];
    }
}
