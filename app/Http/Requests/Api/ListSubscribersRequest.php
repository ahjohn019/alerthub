<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSubscribersRequest extends FormRequest
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
            'includes' => ['sometimes', 'string'],
        ];
    }
}
