<?php

use App\Models\AgentType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Types')] #[Layout('layouts.app')] class extends Component {
    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function agentTypes(): \Illuminate\Database\Eloquent\Collection
    {
        return AgentType::query()
            ->withCount('agents')
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Agent Types') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage the roles available to agents.') }}</flux:text>
        </div>
        <a href="{{ route('agent-types.create') }}" wire:navigate data-test="create-agent-type-button">
            <flux:button variant="primary">{{ __('New Agent Type') }}</flux:button>
        </a>
    </div>

    @if ($this->agentTypes->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Agent Types') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No agent types have been created yet.') }}</flux:text>
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
                    @foreach ($this->agentTypes as $agentType)
                        <a href="{{ route('agent-types.show', $agentType) }}" wire:navigate class="contents" data-test="agent-type-link">
                            <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="agent-type-row" wire:key="agent-type-{{ $agentType->id }}">
                                <td class="px-4 py-3">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $agentType->name }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $agentType->slug }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text class="truncate">{{ Str::limit($agentType->description, 60) }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $agentType->agents_count }}</flux:text>
                                </td>
                            </tr>
                        </a>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
