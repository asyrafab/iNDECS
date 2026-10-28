<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BangunanApplication;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBangunanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canPerformBangunan('register', is_string($this->input('application_type_id')) ? $this->input('application_type_id') : '') ?? false;
    }

    public function rules(): array
    {
        return self::registrationRules($this->user());
    }

    public static function registrationRules(User $actor): array
    {
        $rules = [
            'application_type_id' => ['required', Rule::in(BangunanApplication::TYPES), Rule::exists('application_types', 'id')->where('primary_department_id', 'JBANG')->where('is_active', true)],
            'application_title' => ['required', 'string', 'max:255'],
            'application_description' => ['nullable', 'string', 'max:5000'],
            'external_reference' => ['required_if:application_type_id,PB', 'nullable', 'string', 'max:255'],
            'submission_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'priority' => ['required', Rule::in(['normal', 'high', 'urgent'])],
            'applicant' => ['required', 'array:party_type,name,registration_number,email,phone,address'],
            'applicant.party_type' => ['required', Rule::in(['individual', 'organisation'])],
            'applicant.name' => ['required', 'string', 'max:255'],
            'applicant.registration_number' => ['nullable', 'string', 'max:100'],
            'applicant.email' => ['nullable', 'email', 'max:255'],
            'applicant.phone' => ['nullable', 'string', 'max:40'],
            'applicant.address' => ['nullable', 'string', 'max:2000'],
            'consultant' => ['nullable', 'array:party_type,name,registration_number,email,phone,address'],
            'consultant.party_type' => ['required_with:consultant.name', 'nullable', Rule::in(['individual', 'organisation'])],
            'consultant.name' => ['nullable', 'string', 'max:255'],
            'consultant.registration_number' => ['nullable', 'string', 'max:100'],
            'consultant.email' => ['nullable', 'email', 'max:255'],
            'consultant.phone' => ['nullable', 'string', 'max:40'],
            'consultant.address' => ['nullable', 'string', 'max:2000'],
            'site' => ['required', 'array:development_address,district,mukim'],
            'site.development_address' => ['required', 'string', 'max:2000'],
            'site.district' => ['required', Rule::in($actor->role !== 'system_admin' && filled($actor->district) ? [$actor->district] : BangunanApplication::DISTRICTS)],
            'site.mukim' => ['required', 'string', 'max:100'],
            'lots' => ['required', 'array', 'min:1', 'max:50'],
            'lots.*' => ['required', 'array:lot_number'],
            'lots.*.lot_number' => ['required', 'string', 'max:100', 'distinct'],
            'bangunan' => ['required', 'array:building_category,building_use,number_of_storeys,number_of_units,gross_floor_area,technical_notes'],
            'bangunan.building_category' => ['required', 'string', 'max:100'],
            'bangunan.building_use' => ['required', 'string', 'max:255'],
            'bangunan.number_of_storeys' => ['nullable', 'integer', 'min:1', 'max:999'],
            'bangunan.number_of_units' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'bangunan.gross_floor_area' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'bangunan.technical_notes' => ['nullable', 'string', 'max:5000'],
        ];

        return $rules;
    }
}
