<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
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
        return $this->getRules($this->user()?->currentOrganization()?->id);
    }

    /**
     * Get validation rules (usable from Livewire with explicit organization).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public static function getRules(?int $organizationId = null): array
    {
        $teamRule = $organizationId
            ? ['required', Rule::exists('teams', 'id')->where('organization_id', $organizationId)]
            : ['required', 'exists:teams,id'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'agent_type_id' => ['required', 'exists:agent_types,id'],
            'team_id' => $teamRule,
            'model' => ['required', 'string', 'max:255'],
            'confidence_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'tools' => ['nullable', 'string'],
            'capabilities' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:idle,active,error,busy'],
        ];
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
            'agent_type_id.required' => __('Please select an agent type.'),
            'agent_type_id.exists' => __('The selected agent type is invalid.'),
            'team_id.required' => __('Please select a team.'),
            'team_id.exists' => __('The selected team is invalid.'),
            'model.required' => __('The model is required.'),
            'confidence_threshold.required' => __('The confidence threshold is required.'),
            'confidence_threshold.min' => __('The confidence threshold must be between 0 and 1.'),
            'confidence_threshold.max' => __('The confidence threshold must be between 0 and 1.'),
            'status.in' => __('The status must be idle, active, error, or busy.'),
        ];
    }
}
