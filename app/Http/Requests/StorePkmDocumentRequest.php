<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePkmDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }

    public function attributes(): array
    {
        return ['document' => 'dokumen'];
    }
}
