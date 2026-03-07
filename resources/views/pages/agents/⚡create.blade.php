<?php

use App\Http\Requests\StoreAgentRequest;
use App\Models\AgentRole;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Agent')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public ?string $agent_role_id = null;

    public ?string $team_id = null;

    public string $provider = '';

    public string $model = '';

    public string $confidence_threshold = '0.8';

    public string $temperature = '';

    public string $max_steps = '';

    public string $max_tokens = '';

    public string $timeout = '';

    public string $schedule = '';

    public string $scheduled_instructions = '';

    public function mount(): void
    {
        $agentRoleId = request()->query('agent_role');
        $teamId = request()->query('team');
        if ($agentRoleId) {
            $this->agent_role_id = $agentRoleId;
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
            'agent_role_id' => $validated['agent_role_id'],
            'team_id' => $validated['team_id'],
            'model' => $validated['model'],
            'provider' => $validated['provider'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'temperature' => $validated['temperature'] !== '' ? (float) $validated['temperature'] : null,
            'max_steps' => $validated['max_steps'] !== '' ? (int) $validated['max_steps'] : null,
            'max_tokens' => $validated['max_tokens'] !== '' ? (int) $validated['max_tokens'] : null,
            'timeout' => $validated['timeout'] !== '' ? (int) $validated['timeout'] : null,
            'status' => 'idle',
            'schedule' => $validated['schedule'] ?: null,
            'scheduled_instructions' => $validated['scheduled_instructions'] ?: null,
        ]);

        $this->redirect(route('agents.show', $agent), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    public function updatedProvider(): void
    {
        $this->model = '';
    }

    /**
     * @return array<string, array{label: string, models?: array<string, string>}>
     */
    #[Computed]
    public function providers(): array
    {
        return collect(config('agentry.providers'))
            ->filter(fn ($p) => ! empty($p['key']))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function models(): array
    {
        if (! $this->provider) {
            return [];
        }

        return config("agentry.providers.{$this->provider}.models", []);
    }

    #[Computed]
    public function agentRoles(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->organization;

        return $org
            ? AgentRole::query()->where('organization_id', $org->id)->orderBy('name')->get()
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
            <flux:label>{{ __('Agent Role') }}</flux:label>
            <flux:select wire:model="agent_role_id" :placeholder="__('Select agent role...')" data-test="agent-role-input" required>
                @foreach ($this->agentRoles as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="agent_role_id" />
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
            <flux:label>{{ __('Provider') }}</flux:label>
            <flux:select wire:model.live="provider" :placeholder="__('Select provider...')" data-test="agent-provider-input" required>
                @foreach ($this->providers as $key => $p)
                    <flux:select.option :value="$key">{{ $p['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="provider" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Model') }}</flux:label>
            @if (count($this->models) > 0)
                <flux:select wire:model="model" :placeholder="__('Select model...')" data-test="agent-model-input" required>
                    @foreach ($this->models as $id => $label)
                        <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:input wire:model="model" data-test="agent-model-input" placeholder="{{ $this->provider ? __('Enter model ID...') : __('Select a provider first') }}" required :disabled="! $this->provider" />
            @endif
            <flux:error name="model" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Confidence Threshold') }}</flux:label>
            <flux:input wire:model="confidence_threshold" type="number" step="0.01" min="0" max="1" data-test="agent-confidence-input" required />
            <flux:description>{{ __('Value between 0 and 1 (e.g. 0.8 for 80%).') }}</flux:description>
            <flux:error name="confidence_threshold" />
        </flux:field>

        <flux:heading size="md" class="mt-6">{{ __('Optional Overrides') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Override agent role defaults (temperature, max steps, etc.).') }}</flux:text>

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

        <flux:heading size="md" class="mt-6">{{ __('Schedule') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Run this agent on a recurring schedule instead of (or in addition to) event triggers.') }}</flux:text>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Schedule') }}</flux:label>
                <flux:select wire:model="schedule" :placeholder="__('No schedule')" data-test="agent-schedule-input">
                    @foreach (\App\Console\Commands\RunScheduledAgents::SCHEDULES as $preset => $minutes)
                        <flux:select.option :value="$preset">{{ str_replace('_', ' ', ucfirst($preset)) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="schedule" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Scheduled Instructions') }}</flux:label>
            <flux:textarea wire:model="scheduled_instructions" data-test="agent-scheduled-instructions-input" rows="4" placeholder="{{ __('Instructions for the agent when triggered by schedule...') }}" />
            <flux:description>{{ __('What the agent should do each time the schedule fires.') }}</flux:description>
            <flux:error name="scheduled_instructions" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-agent-button">{{ __('Create Agent') }}</flux:button>
            <a href="{{ route('teams.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
