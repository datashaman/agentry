<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRoleRequest extends FormRequest
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
        return self::getRules($this->user()?->currentOrganization()?->id);
    }

    /**
     * Get validation rules (usable from Livewire with explicit organization).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public static function getRules(?int $organizationId = null): array
    {
        $slugRule = ['required', 'string', 'max:255'];
        if ($organizationId !== null) {
            $slugRule[] = Rule::unique('agent_roles', 'slug')->where('organization_id', $organizationId);
        } else {
            $slugRule[] = 'unique:agent_roles,slug';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:65535'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'default_provider' => ['nullable', 'string', 'max:255'],
            'default_temperature' => ['nullable', 'numeric'],
            'default_max_steps' => ['nullable', 'integer', 'min:1'],
            'default_max_tokens' => ['nullable', 'integer', 'min:1'],
            'default_timeout' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
