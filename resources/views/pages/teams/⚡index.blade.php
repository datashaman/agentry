<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Teams & Agents')] #[Layout('layouts.app')] class extends Component {
    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function teams(): \Illuminate\Support\Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return $this->organization->teams()
            ->withCount('agents')
            ->with(['agents.agentType'])
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('Teams & Agents') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Agent teams and their members in your organization.') }}</flux:text>
    </div>

    @if ($this->teams->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Teams') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No teams found in your organization.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($this->teams as $team)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="team-card" wire:key="team-{{ $team->id }}">
                    <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                        <div>
                            <flux:heading size="lg">{{ $team->name }}</flux:heading>
                            <flux:text class="mt-0.5">{{ $team->agents_count }} {{ Str::plural('agent', $team->agents_count) }}</flux:text>
                        </div>
                    </div>

                    @if ($team->agents->isEmpty())
                        <div class="px-6 py-4">
                            <flux:text>{{ __('No agents in this team.') }}</flux:text>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</th>
                                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                                        <th class="px-6 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Confidence') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($team->agents as $agent)
                                        <tr class="border-b border-zinc-200 last:border-b-0 dark:border-zinc-700" data-test="agent-row" wire:key="agent-{{ $agent->id }}">
                                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100" data-test="agent-name">{{ $agent->name }}</td>
                                            <td class="px-6 py-3">
                                                <flux:badge size="sm" variant="pill">{{ $agent->agentType?->name ?? '-' }}</flux:badge>
                                            </td>
                                            <td class="px-6 py-3">
                                                <flux:text>{{ $agent->model }}</flux:text>
                                            </td>
                                            <td class="px-6 py-3">
                                                <flux:badge size="sm" variant="pill" :color="match($agent->status) { 'active' => 'green', 'idle' => 'zinc', 'error' => 'red', default => 'amber' }">{{ ucfirst($agent->status) }}</flux:badge>
                                            </td>
                                            <td class="px-6 py-3">
                                                <flux:text>{{ round($agent->confidence_threshold * 100) }}%</flux:text>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
