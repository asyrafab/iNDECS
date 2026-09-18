<?php

namespace App\Http\Requests;

use App\Enums\CompletenessItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompletenessItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CompletenessItemStatus::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
