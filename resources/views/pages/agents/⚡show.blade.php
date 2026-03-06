<?php

use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent')] #[Layout('layouts.app')] class extends Component {
    public Agent $agent;

    public function mount(): void
    {
        $this->agent->load(['agentRole', 'team']);
        $this->agent->load([
            'assignedStories.epic.project',
            'assignedBugs.project',
            'assignedOpsRequests.project',
        ]);
        $this->agent->setRelation(
            'actionLogs',
            $this->agent->actionLogs()
                ->with('workItem')
                ->latest('timestamp')
                ->limit(20)
                ->get()
        );
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    public function storyUrl(\App\Models\Story $story): string
    {
        $project = $story->epic?->project;

        return $project
            ? route('projects.stories.show', [$project, $story])
            : '#';
    }

    public function bugUrl(\App\Models\Bug $bug): string
    {
        return route('projects.bugs.show', [$bug->project, $bug]);
    }

    public function opsRequestUrl(\App\Models\OpsRequest $opsRequest): string
    {
        return route('projects.ops-requests.show', [$opsRequest->project, $opsRequest]);
    }

    public function deleteAgent(): void
    {
        $this->agent->delete();

        $this->redirect(route('teams.index'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between" data-test="agent-header">
        <div>
            <flux:heading size="xl">{{ $agent->name }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $agent->agentRole?->name ?? '-' }}</flux:badge>
                <a href="{{ $agent->team ? route('teams.show', $agent->team) : '#' }}" wire:navigate class="text-sm hover:underline">{{ $agent->team?->name ?? __('No team') }}</a>
                <flux:text class="text-sm">{{ $agent->model }}</flux:text>
                <flux:text class="text-sm">{{ $agent->provider }}</flux:text>
                <flux:badge size="sm" variant="pill" :color="match($agent->status) { 'active' => 'green', 'idle' => 'zinc', 'error' => 'red', default => 'amber' }">{{ ucfirst($agent->status) }}</flux:badge>
                <flux:text class="text-sm">{{ __('Confidence: :threshold', ['threshold' => round(($agent->confidence_threshold ?? 0) * 100).'%']) }}</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('agents.edit', $agent) }}" wire:navigate data-test="edit-agent-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-agent-deletion">
                <flux:button variant="danger" data-test="delete-agent-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-agent-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this agent?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone. The agent ":name" will be permanently deleted.', ['name' => $agent->name]) }}</flux:text>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteAgent" data-test="confirm-delete-agent-button">
                    {{ __('Delete Agent') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Overrides --}}
    @if ($agent->temperature !== null || $agent->max_steps || $agent->max_tokens || $agent->timeout)
        <div data-test="agent-overrides">
            <flux:heading size="lg">{{ __('Overrides') }}</flux:heading>
            <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($agent->temperature !== null)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Temperature') }}</flux:text>
                        <flux:text class="block">{{ $agent->temperature }}</flux:text>
                    </div>
                @endif
                @if ($agent->max_steps)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Steps') }}</flux:text>
                        <flux:text class="block">{{ $agent->max_steps }}</flux:text>
                    </div>
                @endif
                @if ($agent->max_tokens)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Tokens') }}</flux:text>
                        <flux:text class="block">{{ $agent->max_tokens }}</flux:text>
                    </div>
                @endif
                @if ($agent->timeout)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Timeout (seconds)') }}</flux:text>
                        <flux:text class="block">{{ $agent->timeout }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Currently Assigned Work --}}
    <div data-test="agent-assignments">
        <flux:heading size="lg">{{ __('Currently Assigned') }}</flux:heading>
        <div class="mt-2 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Stories') }}</flux:text>
                @if ($agent->assignedStories->isEmpty())
                    <flux:text class="mt-1">{{ __('None') }}</flux:text>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($agent->assignedStories as $story)
                            <li>
                                <a href="{{ $this->storyUrl($story) }}" wire:navigate class="text-sm font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $story->title }}</a>
                                <flux:badge size="sm" variant="pill" class="ml-1">{{ $story->status }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Bugs') }}</flux:text>
                @if ($agent->assignedBugs->isEmpty())
                    <flux:text class="mt-1">{{ __('None') }}</flux:text>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($agent->assignedBugs as $bug)
                            <li>
                                <a href="{{ $this->bugUrl($bug) }}" wire:navigate class="text-sm font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $bug->title }}</a>
                                <flux:badge size="sm" variant="pill" class="ml-1">{{ $bug->status }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ops Requests') }}</flux:text>
                @if ($agent->assignedOpsRequests->isEmpty())
                    <flux:text class="mt-1">{{ __('None') }}</flux:text>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($agent->assignedOpsRequests as $opsRequest)
                            <li>
                                <a href="{{ $this->opsRequestUrl($opsRequest) }}" wire:navigate class="text-sm font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $opsRequest->title }}</a>
                                <flux:badge size="sm" variant="pill" class="ml-1">{{ $opsRequest->status }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent Action Logs --}}
    <div data-test="agent-action-logs">
        <flux:heading size="lg">{{ __('Recent Activity') }} ({{ $agent->actionLogs->count() }})</flux:heading>
        @if ($agent->actionLogs->isEmpty())
            <flux:text class="mt-2">{{ __('No recent activity.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Timestamp') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Action') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Work Item') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Reasoning') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agent->actionLogs as $log)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="log-{{ $log->id }}" data-test="action-log-row">
                                <td class="px-4 py-3">
                                    <flux:text>{{ $log->timestamp?->format('Y-m-d H:i:s') ?? '-' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $log->action }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($log->workItem)
                                        @php
                                            $workItem = $log->workItem;
                                            $type = class_basename($workItem);
                                            $title = $workItem->title ?? $workItem->id ?? '-';
                                            $url = match ($type) {
                                                'Story' => $workItem->epic?->project ? route('projects.stories.show', [$workItem->epic->project, $workItem]) : null,
                                                'Bug' => route('projects.bugs.show', [$workItem->project, $workItem]),
                                                'OpsRequest' => route('projects.ops-requests.show', [$workItem->project, $workItem]),
                                                default => null,
                                            };
                                        @endphp
                                        @if ($url)
                                            <a href="{{ $url }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $type }}: {{ $title }}</a>
                                        @else
                                            <flux:text>{{ $type }}: {{ $title }}</flux:text>
                                        @endif
                                    @else
                                        <flux:text>-</flux:text>
                                    @endif
                                </td>
                                <td class="max-w-xs px-4 py-3">
                                    <flux:text class="truncate">{{ Str::limit($log->reasoning, 80) }}</flux:text>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
