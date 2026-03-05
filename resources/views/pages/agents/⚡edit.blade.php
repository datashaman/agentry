<?php

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Agent')] #[Layout('layouts.app')] class extends Component {
    public Agent $agent;

    public string $name = '';

    public ?string $agent_type_id = null;

    public ?string $team_id = null;

    public string $model = '';

    public string $confidence_threshold = '';

    public string $provider = '';

    public string $temperature = '';

    public string $max_steps = '';

    public string $max_tokens = '';

    public string $timeout = '';

    public string $status = '';

    public function mount(): void
    {
        $this->name = $this->agent->name;
        $this->agent_type_id = (string) $this->agent->agent_type_id;
        $this->team_id = (string) $this->agent->team_id;
        $this->model = $this->agent->model;
        $this->confidence_threshold = (string) ($this->agent->confidence_threshold ?? 0.8);
        $this->provider = $this->agent->provider ?? '';
        $this->temperature = $this->agent->temperature !== null ? (string) $this->agent->temperature : '';
        $this->max_steps = $this->agent->max_steps !== null ? (string) $this->agent->max_steps : '';
        $this->max_tokens = $this->agent->max_tokens !== null ? (string) $this->agent->max_tokens : '';
        $this->timeout = $this->agent->timeout !== null ? (string) $this->agent->timeout : '';
        $this->status = $this->agent->status ?? 'idle';
    }

    public function updateAgent(): void
    {
        $org = Auth::user()->currentOrganization();
        $validated = $this->validate(
            StoreAgentRequest::getRules($org?->id),
            (new UpdateAgentRequest())->messages()
        );

        $this->agent->update([
            'name' => $validated['name'],
            'agent_type_id' => $validated['agent_type_id'],
            'team_id' => $validated['team_id'],
            'model' => $validated['model'],
            'provider' => $validated['provider'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'temperature' => $validated['temperature'] !== '' ? (float) $validated['temperature'] : null,
            'max_steps' => $validated['max_steps'] !== '' ? (int) $validated['max_steps'] : null,
            'max_tokens' => $validated['max_tokens'] !== '' ? (int) $validated['max_tokens'] : null,
            'timeout' => $validated['timeout'] !== '' ? (int) $validated['timeout'] : null,
            'status' => $validated['status'],
        ]);

        $this->redirect(route('agents.show', $this->agent), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function agentTypes(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->organization;

        return $org
            ? AgentType::query()->where('organization_id', $org->id)->orderBy('name')->get()
            : collect();
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
        <flux:heading size="xl">{{ __('Edit Agent') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update agent ":name".', ['name' => $agent->name]) }}</flux:text>
    </div>

    <form wire:submit="updateAgent" class="max-w-xl space-y-6" data-test="edit-agent-form">
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
            <flux:label>{{ __('Provider') }}</flux:label>
            <flux:input wire:model="provider" data-test="agent-provider-input" placeholder="e.g. anthropic, openai" required />
            <flux:description>{{ __('AI provider (e.g. anthropic, openai, google).') }}</flux:description>
            <flux:error name="provider" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confidence Threshold') }}</flux:label>
            <flux:input wire:model="confidence_threshold" type="number" step="0.01" min="0" max="1" data-test="agent-confidence-input" required />
            <flux:description>{{ __('Value between 0 and 1 (e.g. 0.8 for 80%).') }}</flux:description>
            <flux:error name="confidence_threshold" />
        </flux:field>

        <flux:heading size="md" class="mt-6">{{ __('Optional Overrides') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Override agent type defaults (temperature, max steps, etc.).') }}</flux:text>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('Temperature') }}</flux:label>
                <flux:input wire:model="temperature" type="number" step="0.01" data-test="agent-temperature-input" placeholder="Provider-dependent" />
                <flux:error name="temperature" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Max Steps') }}</flux:label>
                <flux:input wire:model="max_steps" type="number" min="1" data-test="agent-max-steps-input" placeholder="e.g. 10" />
                <flux:error name="max_steps" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Max Tokens') }}</flux:label>
                <flux:input wire:model="max_tokens" type="number" min="1" data-test="agent-max-tokens-input" placeholder="e.g. 4096" />
                <flux:error name="max_tokens" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Timeout (seconds)') }}</flux:label>
                <flux:input wire:model="timeout" type="number" min="1" data-test="agent-timeout-input" placeholder="e.g. 60" />
                <flux:error name="timeout" />
            </flux:field>
        </div>

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
            <flux:button type="submit" variant="primary" data-test="save-agent-button">{{ __('Update Agent') }}</flux:button>
            <a href="{{ route('agents.show', $agent) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
