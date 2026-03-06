<?php

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Team')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public string $workflow_type = 'none';

    public function createTeam(): void
    {
        $org = Auth::user()->currentOrganization();

        if (! $org) {
            $this->addError('organization', __('Please select an organization first.'));
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'workflow_type' => ['required', Rule::in(['none', 'chain', 'parallel', 'router', 'orchestrator', 'evaluator_optimizer'])],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (Team::where('organization_id', $org->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        $team = Team::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'workflow_type' => $validated['workflow_type'],
        ]);

        $this->redirect(route('teams.show', $team), navigate: true);
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
        <flux:heading size="xl">{{ __('New Team') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Create a new team for your organization.') }}</flux:text>
        @if ($this->organization)
            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organization: :name', ['name' => $this->organization->name]) }}</flux:text>
        @endif
    </div>

    <form wire:submit="createTeam" class="max-w-xl space-y-6" data-test="create-team-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="team-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Workflow Type') }}</flux:label>
            <flux:select wire:model="workflow_type" data-test="workflow-type-select">
                <flux:select.option value="none">{{ __('None (independent agents)') }}</flux:select.option>
                <flux:select.option value="chain">{{ __('Chain (sequential)') }}</flux:select.option>
                <flux:select.option value="parallel">{{ __('Parallel (fan-out)') }}</flux:select.option>
                <flux:select.option value="router">{{ __('Router (dynamic routing)') }}</flux:select.option>
                <flux:select.option value="orchestrator">{{ __('Orchestrator (planner + workers)') }}</flux:select.option>
                <flux:select.option value="evaluator_optimizer">{{ __('Evaluator-Optimizer (refine loop)') }}</flux:select.option>
            </flux:select>
            <flux:description>{{ __('Configure agent assignments after creating the team.') }}</flux:description>
            <flux:error name="workflow_type" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-team-button">{{ __('Create Team') }}</flux:button>
            <a href="{{ route('teams.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
