<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriberRequest extends FormRequest
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
            'email' => [
                'nullable',
                'required_without:external_id',
                'email',
                'max:255',
                Rule::unique('subscribers')->where('project_id', $project->id),
            ],
            'external_id' => [
                'nullable',
                'required_without:email',
                'string',
                'max:255',
                Rule::unique('subscribers')->where('project_id', $project->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
