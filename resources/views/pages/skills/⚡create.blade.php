<?php

use App\Http\Requests\StoreSkillRequest;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Skill')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $content = '';

    public function updatedName(string $value): void
    {
        if ($this->slug === '' || $this->slug === Str::slug($this->name)) {
            $this->slug = Str::slug($value);
        }
    }

    public function createSkill(): void
    {
        $org = $this->organization;

        if (! $org) {
            $this->addError('organization', __('Please select an organization first.'));
            return;
        }

        $validated = $this->validate(StoreSkillRequest::getRules($org->id));

        Skill::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'content' => $validated['content'] ?: null,
        ]);

        $this->redirect(route('skills.index'), navigate: true);
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
        <div>
            <flux:heading size="xl">{{ __('New Skill') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a domain-specific capability package for agents.') }}</flux:text>
            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organization: :name', ['name' => $this->organization->name]) }}</flux:text>
        </div>

    <form wire:submit="createSkill" class="max-w-xl space-y-6" data-test="create-skill-form">
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
            <flux:textarea wire:model="description" data-test="skill-description-input" rows="2" placeholder="{{ __('Brief metadata for discovery—when to use this skill.') }}" />
            <flux:description>{{ __('Lightweight metadata; shown in lists and skill selection.') }}</flux:description>
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Content (Instructions)') }}</flux:label>
            <flux:textarea wire:model="content" data-test="skill-content-input" rows="10" placeholder="{{ __('Main instructions merged into agent context when this skill is assigned.') }}" />
            <flux:description>{{ __('Markdown-supported instructions that extend agent behavior.') }}</flux:description>
            <flux:error name="content" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-skill-button">{{ __('Create Skill') }}</flux:button>
            <a href="{{ route('skills.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to create skills.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
