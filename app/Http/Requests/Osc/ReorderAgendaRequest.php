<?php

declare(strict_types=1);

namespace App\Http\Requests\Osc;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderAgendaRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'size:26', 'distinct'],
        ];
    }
}
