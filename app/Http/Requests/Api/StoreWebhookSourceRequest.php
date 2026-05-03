<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'source_key' => [
                'required',
                'alpha_dash',
                'max:255',
                Rule::unique('webhook_sources')->where('project_id', $project->id),
            ],
            'source_type' => ['required', Rule::in(['github', 'stripe', 'monitoring', 'custom'])],
            'name' => ['required', 'string', 'max:255'],
            'signing_secret' => ['nullable', 'string', 'max:255'],
            'event_mappings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
