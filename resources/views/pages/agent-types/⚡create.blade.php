<?php

use App\Http\Requests\StoreAgentTypeRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Agent Type')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $instructions = '';

    public string $tools = '';

    public string $default_model = '';

    public string $default_provider = '';

    public string $default_temperature = '';

    public string $default_max_steps = '';

    public string $default_max_tokens = '';

    public string $default_timeout = '';

    public function createAgentType(): void
    {
        $org = $this->organization;

        if (! $org) {
            $this->addError('organization', __('Please select an organization first.'));
            return;
        }

        $validated = $this->validate(StoreAgentTypeRequest::getRules($org->id));

        $agentType = \App\Models\AgentType::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'instructions' => $validated['instructions'] ?: null,
            'tools' => $validated['tools'] ? array_map('trim', explode(',', $validated['tools'])) : [],
            'default_model' => $validated['default_model'] ?: null,
            'default_provider' => $validated['default_provider'] ?: null,
            'default_temperature' => $validated['default_temperature'] !== '' ? (float) $validated['default_temperature'] : null,
            'default_max_steps' => $validated['default_max_steps'] !== '' ? (int) $validated['default_max_steps'] : null,
            'default_max_tokens' => $validated['default_max_tokens'] !== '' ? (int) $validated['default_max_tokens'] : null,
            'default_timeout' => $validated['default_timeout'] !== '' ? (int) $validated['default_timeout'] : null,
        ]);

        $this->redirect(route('agent-types.show', $agentType), navigate: true);
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
            <flux:heading size="xl">{{ __('New Agent Type') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a new agent type to define roles for agents.') }}</flux:text>
            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organization: :name', ['name' => $this->organization->name]) }}</flux:text>
        </div>

    <form wire:submit="createAgentType" class="max-w-xl space-y-6" data-test="create-agent-type-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="agent-type-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" data-test="agent-type-slug-input" required />
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="agent-type-description-input" rows="3" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Instructions') }}</flux:label>
            <flux:textarea wire:model="instructions" data-test="agent-type-instructions-input" rows="5" placeholder="System prompt for this agent type..." />
            <flux:description>{{ __('System prompt or instructions that define agent behavior.') }}</flux:description>
            <flux:error name="instructions" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tools') }}</flux:label>
            <flux:input wire:model="tools" data-test="agent-type-tools-input" placeholder="tool1, tool2, ..." />
            <flux:description>{{ __('Comma-separated list of tool IDs this type can use.') }}</flux:description>
            <flux:error name="tools" />
        </flux:field>

        <flux:heading size="md" class="mt-6">{{ __('Default Config') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Optional defaults that agents of this type can inherit.') }}</flux:text>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Default Model') }}</flux:label>
                <flux:input wire:model="default_model" data-test="agent-type-default-model-input" placeholder="e.g. claude-sonnet-4" />
                <flux:error name="default_model" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Provider') }}</flux:label>
                <flux:input wire:model="default_provider" data-test="agent-type-default-provider-input" placeholder="e.g. anthropic" />
                <flux:error name="default_provider" />
            </flux:field>
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('Default Temperature') }}</flux:label>
                <flux:input wire:model="default_temperature" type="number" step="0.01" data-test="agent-type-default-temperature-input" placeholder="Provider-dependent" />
                <flux:description>{{ __('Provider-dependent; some providers ignore this.') }}</flux:description>
                <flux:error name="default_temperature" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Steps') }}</flux:label>
                <flux:input wire:model="default_max_steps" type="number" min="1" data-test="agent-type-default-max-steps-input" placeholder="e.g. 10" />
                <flux:error name="default_max_steps" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Tokens') }}</flux:label>
                <flux:input wire:model="default_max_tokens" type="number" min="1" data-test="agent-type-default-max-tokens-input" placeholder="e.g. 4096" />
                <flux:error name="default_max_tokens" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Timeout (seconds)') }}</flux:label>
                <flux:input wire:model="default_timeout" type="number" min="1" data-test="agent-type-default-timeout-input" placeholder="e.g. 60" />
                <flux:error name="default_timeout" />
            </flux:field>
        </div>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-agent-type-button">{{ __('Create Agent Type') }}</flux:button>
            <a href="{{ route('agent-types.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to create agent types.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
