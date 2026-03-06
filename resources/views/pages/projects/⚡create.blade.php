<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Project')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public function createProject(): void
    {
        $org = Auth::user()->currentOrganization();

        if (! $org) {
            $this->addError('organization', __('Please select an organization first.'));
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (Project::where('organization_id', $org->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        $project = Project::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $this->redirect(route('projects.show', $project), navigate: true);
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
        <flux:heading size="xl">{{ __('New Project') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Create a new project for your organization.') }}</flux:text>
        @if ($this->organization)
            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organization: :name', ['name' => $this->organization->name]) }}</flux:text>
        @endif
    </div>

    <form wire:submit="createProject" class="max-w-xl space-y-6" data-test="create-project-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="project-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-project-button">{{ __('Create Project') }}</flux:button>
            <a href="{{ route('projects.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
