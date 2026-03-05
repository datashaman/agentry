<?php

use App\Http\Requests\StoreAgentRequest;
use App\Models\AgentType;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Agent')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public ?string $agent_type_id = null;

    public ?string $team_id = null;

    public string $model = '';

    public string $confidence_threshold = '0.8';

    public string $tools = '';

    public string $capabilities = '';

    public string $status = 'idle';

    public function mount(): void
    {
        $agentTypeId = request()->query('agent_type');
        $teamId = request()->query('team');
        if ($agentTypeId) {
            $this->agent_type_id = $agentTypeId;
        }
        if ($teamId) {
            $this->team_id = $teamId;
        }
    }

    public function createAgent(): void
    {
        $org = Auth::user()->currentOrganization();
        $validated = $this->validate(
            StoreAgentRequest::getRules($org?->id),
            (new StoreAgentRequest())->messages()
        );

        $agent = \App\Models\Agent::create([
            'name' => $validated['name'],
            'agent_type_id' => $validated['agent_type_id'],
            'team_id' => $validated['team_id'],
            'model' => $validated['model'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'tools' => $validated['tools'] ? array_map('trim', explode(',', $validated['tools'])) : [],
            'capabilities' => $validated['capabilities'] ? array_map('trim', explode(',', $validated['capabilities'])) : [],
            'status' => $validated['status'],
        ]);

        $this->redirect(route('agents.show', $agent), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function agentTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AgentType::query()->orderBy('name')->get();
    }

    #[Computed]
    public function teams(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->organization;

        return $org
            ? Team::query()->where('organization_id', $org->id)->orderBy('name')->get()
            : collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('New Agent') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Create a new agent for your team.') }}</flux:text>
    </div>

    <form wire:submit="createAgent" class="max-w-xl space-y-6" data-test="create-agent-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="agent-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Agent Type') }}</flux:label>
            <flux:select wire:model="agent_type_id" :placeholder="__('Select agent type...')" data-test="agent-type-input" required>
                @foreach ($this->agentTypes as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="agent_type_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Team') }}</flux:label>
            <flux:select wire:model="team_id" :placeholder="__('Select team...')" data-test="agent-team-input" required>
                @foreach ($this->teams as $team)
                    <flux:select.option :value="$team->id">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="team_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Model') }}</flux:label>
            <flux:input wire:model="model" data-test="agent-model-input" placeholder="e.g. claude-opus-4-6" required />
            <flux:error name="model" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confidence Threshold') }}</flux:label>
            <flux:input wire:model="confidence_threshold" type="number" step="0.01" min="0" max="1" data-test="agent-confidence-input" required />
            <flux:description>{{ __('Value between 0 and 1 (e.g. 0.8 for 80%).') }}</flux:description>
            <flux:error name="confidence_threshold" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tools') }}</flux:label>
            <flux:input wire:model="tools" data-test="agent-tools-input" placeholder="tool1, tool2, ..." />
            <flux:description>{{ __('Comma-separated list of tools.') }}</flux:description>
            <flux:error name="tools" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Capabilities') }}</flux:label>
            <flux:input wire:model="capabilities" data-test="agent-capabilities-input" placeholder="capability1, capability2, ..." />
            <flux:description>{{ __('Comma-separated list of capabilities.') }}</flux:description>
            <flux:error name="capabilities" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Status') }}</flux:label>
            <flux:select wire:model="status" data-test="agent-status-input">
                <flux:select.option value="idle">{{ __('Idle') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="error">{{ __('Error') }}</flux:select.option>
                <flux:select.option value="busy">{{ __('Busy') }}</flux:select.option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-agent-button">{{ __('Create Agent') }}</flux:button>
            <a href="{{ route('teams.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
