<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
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
        $skill = $this->route('skill');
        $orgId = $skill?->organization_id ?? $this->user()?->currentOrganization()?->id;

        return self::getRules($skill?->id, $orgId);
    }

    /**
     * Get validation rules (usable from Livewire).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public static function getRules(?int $skillId = null, ?int $organizationId = null): array
    {
        $slugRule = ['required', 'string', 'max:255'];
        if ($skillId !== null && $organizationId !== null) {
            $slugRule[] = Rule::unique('skills', 'slug')->where('organization_id', $organizationId)->ignore($skillId);
        } elseif ($skillId !== null) {
            $slugRule[] = Rule::unique('skills', 'slug')->ignore($skillId);
        } else {
            $slugRule[] = 'unique:skills,slug';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRule,
            'description' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
