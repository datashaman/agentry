<?php

use App\Http\Requests\UpdateAgentTypeRequest;
use App\Models\AgentType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Agent Type')] #[Layout('layouts.app')] class extends Component {
    public AgentType $agentType;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $default_capabilities = '';

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->agentType->organization_id !== $org->id) {
            abort(403);
        }

        $this->name = $this->agentType->name;
        $this->slug = $this->agentType->slug;
        $this->description = $this->agentType->description ?? '';
        $this->default_capabilities = implode(', ', $this->agentType->default_capabilities ?? []);
    }

    public function updateAgentType(): void
    {
        $org = $this->organization;
        if (! $org || $this->agentType->organization_id !== $org->id) {
            abort(403);
        }

        $validated = $this->validate(UpdateAgentTypeRequest::getRules($this->agentType->id, $org->id));

        $this->agentType->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'default_capabilities' => $validated['default_capabilities']
                ? array_map('trim', explode(',', $validated['default_capabilities']))
                : [],
        ]);

        $this->redirect(route('agent-types.show', $this->agentType), navigate: true);
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
        <flux:heading size="xl">{{ __('Edit Agent Type') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update agent type ":name".', ['name' => $agentType->name]) }}</flux:text>
    </div>

    <form wire:submit="updateAgentType" class="max-w-xl space-y-6" data-test="edit-agent-type-form">
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
            <flux:button type="submit" variant="primary" data-test="save-agent-type-button">{{ __('Update Agent Type') }}</flux:button>
            <a href="{{ route('agent-types.show', $agentType) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
