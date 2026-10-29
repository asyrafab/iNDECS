<?php

declare(strict_types=1);

namespace App\Http\Requests\Osc;

use Illuminate\Foundation\Http\FormRequest;

final class ReturnAgendaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Sebab pengembalian diperlukan.'];
    }
}
