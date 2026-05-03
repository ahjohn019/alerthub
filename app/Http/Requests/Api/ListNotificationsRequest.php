<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(['pending', 'sent', 'failed', 'escalated'])],
            'channel' => ['sometimes', Rule::in(['email', 'webhook'])],
            'subscriber_id' => ['sometimes', 'integer'],
            'alert_rule_id' => ['sometimes', 'integer'],
            'includes' => ['sometimes', 'string'],
        ];
    }
}
