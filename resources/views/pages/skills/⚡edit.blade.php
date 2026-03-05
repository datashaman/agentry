<?php

use App\Http\Requests\UpdateSkillRequest;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Skill')] #[Layout('layouts.app')] class extends Component {
    public Skill $skill;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $content = '';

    public string $context_triggers = '';

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->skill->organization_id !== $org->id) {
            abort(403);
        }

        $this->name = $this->skill->name;
        $this->slug = $this->skill->slug;
        $this->description = $this->skill->description ?? '';
        $this->content = $this->skill->content ?? '';
        $this->context_triggers = $this->skill->context_triggers !== null ? json_encode($this->skill->context_triggers, JSON_PRETTY_PRINT) : '';
    }

    public function updatedName(string $value): void
    {
        if ($this->slug === '' || $this->slug === Str::slug($this->name)) {
            $this->slug = Str::slug($value);
        }
    }

    public function updateSkill(): void
    {
        $org = $this->organization;
        if (! $org || $this->skill->organization_id !== $org->id) {
            abort(403);
        }

        $validated = $this->validate(UpdateSkillRequest::getRules($this->skill->id, $org->id));

        $contextTriggers = null;
        if (! empty(trim($validated['context_triggers'] ?? ''))) {
            $decoded = json_decode(trim($validated['context_triggers']), true);
            $contextTriggers = is_array($decoded) ? $decoded : null;
        }

        $this->skill->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'content' => $validated['content'] ?: null,
            'context_triggers' => $contextTriggers,
        ]);

        $this->redirect(route('skills.show', $this->skill), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('Edit Skill') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update skill ":name".', ['name' => $skill->name]) }}</flux:text>
    </div>

    <form wire:submit="updateSkill" class="max-w-xl space-y-6" data-test="edit-skill-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="skill-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" data-test="skill-slug-input" required />
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="skill-description-input" rows="2" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Content (Instructions)') }}</flux:label>
            <flux:textarea wire:model="content" data-test="skill-content-input" rows="10" />
            <flux:error name="content" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Context Triggers (optional JSON)') }}</flux:label>
            <flux:textarea wire:model="context_triggers" data-test="skill-context-triggers-input" rows="4" placeholder='{"repo.primary_language": ["php"], "repo.tags": ["laravel"]}' />
            <flux:error name="context_triggers" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-skill-button">{{ __('Update Skill') }}</flux:button>
            <a href="{{ route('skills.show', $skill) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
