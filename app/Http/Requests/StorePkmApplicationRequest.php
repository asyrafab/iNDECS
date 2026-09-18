<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePkmApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_title' => ['required', 'string', 'max:255'],
            'application_description' => ['nullable', 'string', 'max:5000'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'submission_date' => ['required', 'date'],
            'priority' => ['required', 'in:normal,high,urgent'],

            'applicant.party_type' => ['required', 'in:individual,organisation'],
            'applicant.name' => ['required', 'string', 'max:255'],
            'applicant.registration_number' => ['nullable', 'string', 'max:100'],
            'applicant.email' => ['nullable', 'email', 'max:255'],
            'applicant.phone' => ['nullable', 'string', 'max:40'],
            'applicant.address' => ['nullable', 'string', 'max:2000'],

            'consultant.role' => ['nullable', 'in:principal_submitting_person,town_planner,architect,engineer,consultant'],
            'consultant.party_type' => ['nullable', 'in:individual,organisation'],
            'consultant.name' => ['nullable', 'string', 'max:255'],
            'consultant.registration_number' => ['nullable', 'string', 'max:100'],
            'consultant.email' => ['nullable', 'email', 'max:255'],
            'consultant.phone' => ['nullable', 'string', 'max:40'],
            'consultant.address' => ['nullable', 'string', 'max:2000'],

            'site.development_address' => ['required', 'string', 'max:2000'],
            'site.district' => ['required', 'string', 'max:100'],
            'site.mukim' => ['required', 'string', 'max:100'],
            'site.state' => ['required', 'string', 'max:100'],
            'site.site_area' => ['nullable', 'numeric', 'gt:0'],
            'site.site_area_unit' => ['required', 'in:hectare,square_metre'],

            'lots' => ['required', 'array', 'min:1', 'max:50'],
            'lots.*.lot_number' => ['required', 'string', 'max:100'],
            'lots.*.lot_type' => ['nullable', 'string', 'max:50'],
            'lots.*.section' => ['nullable', 'string', 'max:100'],
            'lots.*.land_area' => ['nullable', 'numeric', 'gt:0'],
            'lots.*.land_area_unit' => ['nullable', 'in:hectare,square_metre'],

            'pkm.permission_category' => ['required', 'string', 'max:100'],
            'pkm.permission_type' => ['required', 'string', 'max:100'],
            'pkm.existing_land_use' => ['required', 'string', 'max:255'],
            'pkm.proposed_land_use' => ['required', 'string', 'max:255'],
            'pkm.density' => ['nullable', 'numeric', 'min:0'],
            'pkm.plot_ratio' => ['nullable', 'numeric', 'min:0'],
            'pkm.number_of_units' => ['nullable', 'integer', 'min:0'],
            'pkm.number_of_storeys' => ['nullable', 'integer', 'min:0', 'max:999'],
            'pkm.building_height' => ['nullable', 'numeric', 'min:0'],
            'pkm.planning_standards' => ['nullable', 'string', 'max:5000'],

            'components' => ['nullable', 'array', 'max:50'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.category' => ['nullable', 'string', 'max:100'],
            'components.*.number_of_units' => ['nullable', 'integer', 'min:0'],
            'components.*.floor_area' => ['nullable', 'numeric', 'min:0'],
            'components.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'application_title' => 'tajuk permohonan',
            'submission_date' => 'tarikh penerimaan',
            'applicant.name' => 'nama pemohon',
            'site.development_address' => 'alamat tapak',
            'site.district' => 'daerah',
            'site.mukim' => 'mukim',
            'lots.*.lot_number' => 'nombor lot',
            'pkm.permission_category' => 'kategori kebenaran merancang',
            'pkm.permission_type' => 'jenis kebenaran merancang',
            'pkm.existing_land_use' => 'guna tanah sedia ada',
            'pkm.proposed_land_use' => 'guna tanah dicadangkan',
            'components.*.name' => 'nama komponen pembangunan',
        ];
    }

    protected function prepareForValidation(): void
    {
        $components = collect($this->input('components', []))
            ->filter(fn (mixed $component): bool => is_array($component) && collect($component)->contains(fn (mixed $value): bool => filled($value)))
            ->values()
            ->all();

        $this->merge(['components' => $components]);
    }
}
