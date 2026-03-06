<?php

use App\Models\AgentRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Role')] #[Layout('layouts.app')] class extends Component {
    public AgentRole $agentRole;

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->agentRole->organization_id !== $org->id) {
            abort(403);
        }

        $this->agentRole->loadCount('agents');
        $this->agentRole->load(['agents.team', 'skills' => fn ($q) => $q->orderByPivot('position'), 'eventResponders']);
    }

    public function deleteAgentRole(): void
    {
        if ($this->agentRole->agents()->count() > 0) {
            return;
        }

        $this->agentRole->delete();

        $this->redirect(route('agent-roles.index'), navigate: true);
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
    <div class="flex items-center justify-between" data-test="agent-role-header">
        <div>
            <flux:heading size="xl">{{ $agentRole->name }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $agentRole->slug }}</flux:badge>
                <flux:text class="text-sm">{{ $agentRole->agents_count }} {{ Str::plural('agent', $agentRole->agents_count) }}</flux:text>
            </div>
            @if ($agentRole->description)
                <flux:text class="mt-2">{{ $agentRole->description }}</flux:text>
            @endif
            @if ($agentRole->instructions)
                <flux:text class="mt-2 whitespace-pre-wrap text-sm text-zinc-500 dark:text-zinc-400">{{ $agentRole->instructions }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('agents.create', ['agent_role' => $agentRole->id]) }}" wire:navigate data-test="create-agent-button">
                <flux:button variant="primary">{{ __('New Agent') }}</flux:button>
            </a>
            <a href="{{ route('agent-roles.edit', $agentRole) }}" wire:navigate data-test="edit-agent-role-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-agent-role-deletion">
                <flux:button variant="danger" data-test="delete-agent-role-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Assigned Skills --}}
    <div data-test="agent-role-skills">
        <flux:heading size="lg">{{ __('Assigned Skills') }} ({{ $agentRole->skills->count() }})</flux:heading>
        @if ($agentRole->skills->isEmpty())
            <flux:text class="mt-2">{{ __('No skills assigned.') }}</flux:text>
        @else
            <div class="mt-2 space-y-2">
                @foreach ($agentRole->skills as $skill)
                    <div class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="skill-{{ $skill->id }}" data-test="assigned-skill-row">
                        <div class="min-w-0 flex-1">
                            <flux:text class="font-medium">{{ $skill->name }}</flux:text>
                            @if ($skill->description)
                                <flux:text class="mt-0.5 block truncate text-sm text-zinc-500 dark:text-zinc-400">{{ Str::limit($skill->description, 60) }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Event Responders --}}
    <div data-test="agent-role-event-responders">
        <flux:heading size="lg">{{ __('Event Responders') }} ({{ $agentRole->eventResponders->count() }})</flux:heading>
        @if ($agentRole->eventResponders->isEmpty())
            <flux:text class="mt-2">{{ __('No event responders configured.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Work Item Type') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Instructions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agentRole->eventResponders as $responder)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="responder-{{ $responder->id }}" data-test="responder-row">
                                <td class="px-4 py-3">
                                    <flux:text>{{ str_replace('_', ' ', ucfirst($responder->work_item_type)) }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $responder->status) }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text class="truncate">{{ Str::limit($responder->instructions, 80) }}</flux:text>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Tools --}}
    <div data-test="agent-role-tools">
        <flux:heading size="lg">{{ __('Tools') }}</flux:heading>
        @if (empty($agentRole->tools))
            <flux:text class="mt-2">{{ __('No tools configured.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agentRole->tools as $tool)
                    <flux:badge size="sm" variant="pill">{{ $tool }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Default Config --}}
    @if ($agentRole->default_model || $agentRole->default_provider || $agentRole->default_temperature !== null || $agentRole->default_max_steps || $agentRole->default_max_tokens || $agentRole->default_timeout)
        <div data-test="agent-role-default-config">
            <flux:heading size="lg">{{ __('Default Config') }}</flux:heading>
            <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @if ($agentRole->default_model)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_model }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_provider)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_provider }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_temperature !== null)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Temperature') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_temperature }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_max_steps)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Steps') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_max_steps }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_max_tokens)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Tokens') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_max_tokens }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_timeout)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Timeout (seconds)') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_timeout }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Agents --}}
    <div data-test="agent-role-agents">
        <flux:heading size="lg">{{ __('Agents') }} ({{ $agentRole->agents_count }})</flux:heading>
        @if ($agentRole->agents->isEmpty())
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
                        @foreach ($agentRole->agents as $agent)
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
    <flux:modal name="confirm-agent-role-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this agent role?') }}</flux:heading>
                @if ($agentRole->agents_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This agent role has assigned agents and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The agent role ":name" will be permanently deleted.', ['name' => $agentRole->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($agentRole->agents_count === 0)
                    <flux:button variant="danger" wire:click="deleteAgentRole" data-test="confirm-delete-agent-role-button">
                        {{ __('Delete Agent Role') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
