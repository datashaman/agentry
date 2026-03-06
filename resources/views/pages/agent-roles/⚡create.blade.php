<?php

use App\Http\Requests\StoreAgentRoleRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Agent Role')] #[Layout('layouts.app')] class extends Component {
    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $instructions = '';

    /** @var list<string> */
    public array $tools = [];

    public string $default_provider = '';

    public string $default_model = '';

    public string $default_temperature = '';

    public string $default_max_steps = '';

    public string $default_max_tokens = '';

    public string $default_timeout = '';

    public function updatedName(string $value): void
    {
        if ($this->slug === '' || $this->slug === Str::slug($this->name)) {
            $this->slug = Str::slug($value);
        }
    }

    public function createAgentRole(): void
    {
        $org = $this->organization;

        if (! $org) {
            $this->addError('organization', __('Please select an organization first.'));
            return;
        }

        $validated = $this->validate(StoreAgentRoleRequest::getRules($org->id));

        $agentRole = \App\Models\AgentRole::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'instructions' => $validated['instructions'] ?: null,
            'tools' => $validated['tools'] ?? [],
            'default_model' => $validated['default_model'] ?: null,
            'default_provider' => $validated['default_provider'] ?: null,
            'default_temperature' => $validated['default_temperature'] !== '' ? (float) $validated['default_temperature'] : null,
            'default_max_steps' => $validated['default_max_steps'] !== '' ? (int) $validated['default_max_steps'] : null,
            'default_max_tokens' => $validated['default_max_tokens'] !== '' ? (int) $validated['default_max_tokens'] : null,
            'default_timeout' => $validated['default_timeout'] !== '' ? (int) $validated['default_timeout'] : null,
        ]);

        $this->redirect(route('agent-roles.show', $agentRole), navigate: true);
    }

    public function updatedDefaultProvider(): void
    {
        $this->default_model = '';
    }

    /**
     * @return array<string, array{label: string, models?: array<string, string>}>
     */
    #[Computed]
    public function providers(): array
    {
        return collect(config('ai.providers'))
            ->filter(fn ($p) => ! empty($p['key']))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function models(): array
    {
        if (! $this->default_provider) {
            return [];
        }

        return config("ai.providers.{$this->default_provider}.models", []);
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
            <flux:heading size="xl">{{ __('New Agent Role') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a new agent role to define roles for agents.') }}</flux:text>
            <flux:text class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Organization: :name', ['name' => $this->organization->name]) }}</flux:text>
        </div>

    <form wire:submit="createAgentRole" class="max-w-xl space-y-6" data-test="create-agent-role-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model.live="name" data-test="agent-role-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" data-test="agent-role-slug-input" required />
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="agent-role-description-input" rows="3" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Instructions') }}</flux:label>
            <flux:textarea wire:model="instructions" data-test="agent-role-instructions-input" rows="5" placeholder="System prompt for this agent role..." />
            <flux:description>{{ __('System prompt or instructions that define agent behavior.') }}</flux:description>
            <flux:error name="instructions" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tools') }}</flux:label>
            <div class="mt-1 space-y-2" data-test="agent-role-tools-input">
                @foreach (\App\Agents\ToolRegistry::getProviderTools() as $toolId => $meta)
                    <flux:checkbox wire:model="tools" :value="$toolId" :label="$toolId" :description="$meta['description']" wire:key="tool-{{ $toolId }}" />
                @endforeach
            </div>
            <flux:description>{{ __('Select tools this agent role can use.') }}</flux:description>
            <flux:error name="tools" />
        </flux:field>

        <flux:heading size="md" class="mt-6">{{ __('Default Config') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Optional defaults that agents of this type can inherit.') }}</flux:text>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Default Provider') }}</flux:label>
                <flux:select wire:model.live="default_provider" :placeholder="__('Select provider...')" data-test="agent-role-default-provider-input">
                    @foreach ($this->providers as $key => $p)
                        <flux:select.option :value="$key">{{ $p['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="default_provider" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Model') }}</flux:label>
                @if (count($this->models) > 0)
                    <flux:select wire:model="default_model" :placeholder="__('Select model...')" data-test="agent-role-default-model-input">
                        @foreach ($this->models as $id => $label)
                            <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:input wire:model="default_model" data-test="agent-role-default-model-input" placeholder="{{ $this->default_provider ? __('Enter model ID...') : __('Select a provider first') }}" :disabled="! $this->default_provider" />
                @endif
                <flux:error name="default_model" />
            </flux:field>
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('Default Temperature') }}</flux:label>
                <flux:input wire:model="default_temperature" type="number" step="0.01" data-test="agent-role-default-temperature-input" placeholder="e.g. 0.7" />
                <flux:error name="default_temperature" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Steps') }}</flux:label>
                <flux:input wire:model="default_max_steps" type="number" min="1" data-test="agent-role-default-max-steps-input" placeholder="e.g. 10" />
                <flux:error name="default_max_steps" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Tokens') }}</flux:label>
                <flux:input wire:model="default_max_tokens" type="number" min="1" data-test="agent-role-default-max-tokens-input" placeholder="e.g. 4096" />
                <flux:error name="default_max_tokens" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Timeout (seconds)') }}</flux:label>
                <flux:input wire:model="default_timeout" type="number" min="1" data-test="agent-role-default-timeout-input" placeholder="e.g. 60" />
                <flux:error name="default_timeout" />
            </flux:field>
        </div>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-agent-role-button">{{ __('Create Agent Role') }}</flux:button>
            <a href="{{ route('agent-roles.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to create agent roles.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
