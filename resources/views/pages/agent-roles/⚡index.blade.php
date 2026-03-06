<?php

use App\Models\AgentRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Roles')] #[Layout('layouts.app')] class extends Component {
    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function agentRoles(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return AgentRole::query()
            ->where('organization_id', $this->organization->id)
            ->withCount('agents')
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Agent Roles') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage the roles available to agents.') }}</flux:text>
        </div>
        <a href="{{ route('agent-roles.create') }}" wire:navigate data-test="create-agent-role-button">
            <flux:button variant="primary">{{ __('New Agent Role') }}</flux:button>
        </a>
    </div>

    @if ($this->agentRoles->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Agent Roles') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No agent roles have been created yet.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Slug') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Agents') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->agentRoles as $agentRole)
                        <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="agent-role-row" wire:key="agent-role-{{ $agentRole->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('agent-roles.show', $agentRole) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="agent-role-link">
                                    {{ $agentRole->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ $agentRole->slug }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="truncate">{{ Str::limit($agentRole->description, 60) }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $agentRole->agents_count }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to manage agent roles.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
