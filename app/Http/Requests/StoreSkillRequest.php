<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
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
     * Get validation rules (usable from Livewire).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public static function getRules(?int $organizationId = null): array
    {
        $slugRule = ['required', 'string', 'max:255'];
        if ($organizationId !== null) {
            $slugRule[] = Rule::unique('skills', 'slug')->where('organization_id', $organizationId);
        } else {
            $slugRule[] = 'unique:skills,slug';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string', 'max:65535'],
            'context_triggers' => ['nullable', 'string', 'max:5000', 'json'],
        ];
    }
}
