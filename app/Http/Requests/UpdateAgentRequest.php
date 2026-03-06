<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreAgentRequest::getRules($this->user()?->currentOrganization()?->id);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The agent name is required.'),
            'agent_role_id.required' => __('Please select an agent role.'),
            'agent_role_id.exists' => __('The selected agent role is invalid.'),
            'team_id.required' => __('Please select a team.'),
            'team_id.exists' => __('The selected team is invalid.'),
            'model.required' => __('The model is required.'),
            'provider.required' => __('The provider is required.'),
            'confidence_threshold.required' => __('The confidence threshold is required.'),
            'confidence_threshold.min' => __('The confidence threshold must be between 0 and 1.'),
            'confidence_threshold.max' => __('The confidence threshold must be between 0 and 1.'),
        ];
    }
}
