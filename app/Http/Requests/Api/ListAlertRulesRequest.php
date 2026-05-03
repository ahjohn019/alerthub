<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAlertRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'pagination' => ['sometimes', Rule::in(['offset', 'cursor'])],
            'cursor' => ['sometimes', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'source_type' => ['sometimes', Rule::in(['github', 'stripe', 'monitoring', 'custom'])],
            'event_type' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'includes' => ['sometimes', 'string'],
        ];
    }
}
