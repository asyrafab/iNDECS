<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\LandscapeApplication;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLandskapApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->canPerformLandskap('register') && $this->user()?->canPerformLandskap('access')) ?? false;
    }

    public function rules(): array
    {
        return self::registrationRules($this->user());
    }

    public static function registrationRules(User $actor): array
    {
        return [
            'application_type_id' => ['required', Rule::in(LandscapeApplication::TYPES), Rule::exists('application_types', 'id')->where('primary_department_id', 'JLAND')->where('is_active', true)],
            'application_title' => ['required', 'string', 'max:255'],
            'application_description' => ['nullable', 'string', 'max:5000'],
            'external_reference' => ['nullable', 'string', 'max:255'],
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
            'site' => ['required', 'array:development_address,district,mukim,boundary'],
            'site.boundary' => ['nullable', 'string'],
            'site.development_address' => ['required', 'string', 'max:2000'],
            'site.district' => ['required', Rule::in($actor->role !== 'system_admin' && filled($actor->district) ? [$actor->district] : LandscapeApplication::DISTRICTS)],
            'site.mukim' => ['required', 'string', 'max:100'],
            'lots' => ['required', 'array', 'min:1', 'max:50'],
            'lots.*' => ['required', 'array:lot_number'],
            'lots.*.lot_number' => ['required', 'string', 'max:100', 'distinct'],
            'landskap' => [
                'required',
                'array:landscape_category,open_space_area,softscape_area,hardscape_area,existing_trees,retained_trees,proposed_trees,proposed_species,buffer_planting,recreation_facilities,replacement_conditions',
            ],
            'landskap.landscape_category' => ['required', 'string', 'max:100'],
            'landskap.open_space_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'landskap.softscape_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'landskap.hardscape_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'landskap.existing_trees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'landskap.retained_trees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'landskap.proposed_trees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'landskap.proposed_species' => ['nullable', 'string', 'max:5000'],
            'landskap.buffer_planting' => ['nullable', 'string', 'max:5000'],
            'landskap.recreation_facilities' => ['nullable', 'string', 'max:5000'],
            'landskap.replacement_conditions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
