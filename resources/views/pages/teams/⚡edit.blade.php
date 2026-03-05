<?php

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Team')] #[Layout('layouts.app')] class extends Component {
    public Team $team;

    public string $name = '';

    public function mount(): void
    {
        $this->name = $this->team->name;
    }

    public function updateTeam(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (Team::where('organization_id', $this->team->organization_id)->where('slug', $slug)->where('id', '!=', $this->team->id)->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        $this->team->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $this->redirect(route('teams.show', $this->team), navigate: true);
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
        <flux:heading size="xl">{{ __('Edit Team') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Rename team ":name".', ['name' => $team->name]) }}</flux:text>
    </div>

    <form wire:submit="updateTeam" class="max-w-xl space-y-6" data-test="edit-team-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="team-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-team-button">{{ __('Update Team') }}</flux:button>
            <a href="{{ route('teams.show', $team) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
