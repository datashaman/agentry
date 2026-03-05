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

    public string $default_capabilities = '';

    public function createAgentType(): void
    {
        $validated = $this->validate(StoreAgentTypeRequest::getRules());

        $agentType = \App\Models\AgentType::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'default_capabilities' => $validated['default_capabilities']
                ? array_map('trim', explode(',', $validated['default_capabilities']))
                : [],
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
    @endif

    <div>
        <flux:heading size="xl">{{ __('New Agent Type') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Create a new agent type to define roles for agents.') }}</flux:text>
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
            <flux:label>{{ __('Default Capabilities') }}</flux:label>
            <flux:input wire:model="default_capabilities" data-test="agent-type-capabilities-input" placeholder="capability1, capability2, ..." />
            <flux:description>{{ __('Comma-separated list of default capabilities.') }}</flux:description>
            <flux:error name="default_capabilities" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-agent-type-button">{{ __('Create Agent Type') }}</flux:button>
            <a href="{{ route('agent-types.index') }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
