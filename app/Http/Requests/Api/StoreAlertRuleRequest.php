<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(['github', 'stripe', 'monitoring', 'custom'])],
            'event_type' => ['required', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'action' => ['required', Rule::in(['notify', 'escalate', 'digest'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
