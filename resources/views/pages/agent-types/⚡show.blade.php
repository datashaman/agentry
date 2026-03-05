<?php

use App\Models\AgentType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Type')] #[Layout('layouts.app')] class extends Component {
    public AgentType $agentType;

    public function mount(): void
    {
        $this->agentType->loadCount('agents');
        $this->agentType->load('agents.team');
    }

    public function deleteAgentType(): void
    {
        if ($this->agentType->agents()->count() > 0) {
            return;
        }

        $this->agentType->delete();

        $this->redirect(route('agent-types.index'), navigate: true);
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

    {{-- Header --}}
    <div class="flex items-center justify-between" data-test="agent-type-header">
        <div>
            <flux:heading size="xl">{{ $agentType->name }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $agentType->slug }}</flux:badge>
                <flux:text class="text-sm">{{ $agentType->agents_count }} {{ Str::plural('agent', $agentType->agents_count) }}</flux:text>
            </div>
            @if ($agentType->description)
                <flux:text class="mt-2">{{ $agentType->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('agent-types.edit', $agentType) }}" wire:navigate data-test="edit-agent-type-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-agent-type-deletion">
                <flux:button variant="danger" data-test="delete-agent-type-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Default Capabilities --}}
    <div data-test="default-capabilities">
        <flux:heading size="lg">{{ __('Default Capabilities') }}</flux:heading>
        @if (empty($agentType->default_capabilities))
            <flux:text class="mt-2">{{ __('No default capabilities defined.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agentType->default_capabilities as $capability)
                    <flux:badge size="sm" variant="pill">{{ $capability }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Agents --}}
    <div data-test="agent-type-agents">
        <flux:heading size="lg">{{ __('Agents') }} ({{ $agentType->agents_count }})</flux:heading>
        @if ($agentType->agents->isEmpty())
            <flux:text class="mt-2">{{ __('No agents of this type.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Team') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agentType->agents as $agent)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="agent-{{ $agent->id }}" data-test="agent-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('agents.show', $agent) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $agent->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $agent->team?->name ?? '-' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $agent->model }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill" :color="match($agent->status) { 'active' => 'green', 'idle' => 'zinc', 'error' => 'red', default => 'amber' }">{{ ucfirst($agent->status) }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-agent-type-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this agent type?') }}</flux:heading>
                @if ($agentType->agents_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This agent type has assigned agents and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The agent type ":name" will be permanently deleted.', ['name' => $agentType->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($agentType->agents_count === 0)
                    <flux:button variant="danger" wire:click="deleteAgentType" data-test="confirm-delete-agent-type-button">
                        {{ __('Delete Agent Type') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
