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
        $this->agent->load(['agentType', 'team']);
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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    {{-- Header --}}
    <div data-test="agent-header">
        <flux:heading size="xl">{{ $agent->name }}</flux:heading>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:badge size="sm" variant="pill">{{ $agent->agentType?->name ?? '-' }}</flux:badge>
            <flux:text class="text-sm">{{ $agent->team?->name ?? __('No team') }}</flux:text>
            <flux:text class="text-sm">{{ $agent->model }}</flux:text>
            <flux:badge size="sm" variant="pill" :color="match($agent->status) { 'active' => 'green', 'idle' => 'zinc', 'error' => 'red', default => 'amber' }">{{ ucfirst($agent->status) }}</flux:badge>
            <flux:text class="text-sm">{{ __('Confidence: :threshold', ['threshold' => round(($agent->confidence_threshold ?? 0) * 100).'%']) }}</flux:text>
        </div>
    </div>

    {{-- Capabilities --}}
    <div data-test="agent-capabilities">
        <flux:heading size="lg">{{ __('Capabilities') }}</flux:heading>
        @if (empty($agent->capabilities))
            <flux:text class="mt-2">{{ __('No capabilities defined.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agent->capabilities as $capability)
                    <flux:badge size="sm" variant="pill">{{ $capability }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Tools --}}
    <div data-test="agent-tools">
        <flux:heading size="lg">{{ __('Tools') }}</flux:heading>
        @if (empty($agent->tools))
            <flux:text class="mt-2">{{ __('No tools configured.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agent->tools as $tool)
                    <flux:badge size="sm" variant="pill">{{ $tool }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

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
