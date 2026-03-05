<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentTypeRequest extends FormRequest
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
        $agentType = $this->route('agentType');

        return self::getRules($agentType?->id, $this->user()?->currentOrganization()?->id);
    }

    /**
     * Get validation rules (usable from Livewire).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public static function getRules(?int $agentTypeId = null, ?int $organizationId = null): array
    {
        $slugRule = ['required', 'string', 'max:255'];

        if ($agentTypeId !== null && $organizationId !== null) {
            $slugRule[] = Rule::unique('agent_types', 'slug')->where('organization_id', $organizationId)->ignore($agentTypeId);
        } elseif ($agentTypeId !== null) {
            $slugRule[] = Rule::unique('agent_types', 'slug')->ignore($agentTypeId);
        } else {
            $slugRule[] = 'unique:agent_types,slug';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string', 'max:5000'],
            'default_capabilities' => ['nullable', 'string'],
        ];
    }
}
