<?php

use App\Agents\ChatAgent;
use App\Events\WorkItemClassified;
use App\Models\HitlEscalation;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Item Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public WorkItem $workItem;

    public string $newMessage = '';

    public function mount(): void
    {
        $this->workItem->loadMissing([
            'agentConversations',
            'hitlEscalations.raisedByAgent',
        ]);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function chatMessages(): \Illuminate\Database\Eloquent\Collection
    {
        $conversation = $this->workItem->latestConversation();

        if (! $conversation) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return $conversation->messages()
            ->where('role', '!=', 'system')
            ->oldest()
            ->get();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'newMessage' => 'required|string|min:1',
        ]);

        $user = Auth::user();
        $conversation = $this->workItem->latestConversation();

        $context = $this->buildAgentContext();
        $agent = ChatAgent::make(
            instructions: 'You are a helpful assistant working on a software project work item. '.$context,
        );

        if ($conversation) {
            $agent->continue($conversation->id, $user);
        } else {
            $agent->forUser($user);
        }

        $response = $agent->prompt(
            $this->newMessage,
            provider: config('ai.chat.provider'),
            model: config('ai.chat.model'),
        );

        if (! $conversation && $response->conversationId) {
            $this->workItem->agentConversations()->attach($response->conversationId);
        }

        $this->newMessage = '';
        unset($this->chatMessages);
    }

    protected function buildAgentContext(): string
    {
        $parts = [];
        $parts[] = "Work Item: {$this->workItem->title}";

        if ($this->workItem->description) {
            $parts[] = "Description: {$this->workItem->description}";
        }

        if ($this->workItem->classified_type) {
            $parts[] = "Type: {$this->workItem->classified_type}";
        }

        return implode("\n", $parts);
    }

    public function confirmReclassification(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);

        $escalation->update([
            'resolution' => 'Confirmed reclassification',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->workItem->load('hitlEscalations.raisedByAgent');

        WorkItemClassified::dispatch($this->workItem);
    }

    public function revertReclassification(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);
        $originalType = $escalation->metadata['original_type'] ?? null;

        $this->workItem->update(['classified_type' => $originalType]);

        $escalation->update([
            'resolution' => 'Reverted to original type',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->workItem->load('hitlEscalations.raisedByAgent');

        WorkItemClassified::dispatch($this->workItem->fresh());
    }

    public function approveTypeLabels(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);
        $suggestedLabels = $escalation->metadata['suggested_labels'] ?? [];
        $projectId = $escalation->metadata['project_id'] ?? null;

        if ($projectId && ! empty($suggestedLabels)) {
            $project = \App\Models\Project::find($projectId);

            if ($project) {
                $config = $project->work_item_provider_config ?? [];
                $config['type_labels'] = $suggestedLabels;
                $project->update(['work_item_provider_config' => $config]);
            }
        }

        $escalation->update([
            'resolution' => 'Approved suggested type labels',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->workItem->load('hitlEscalations.raisedByAgent');
    }

    public function rejectTypeLabels(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);

        $escalation->update([
            'resolution' => 'Rejected suggested type labels',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->workItem->load('hitlEscalations.raisedByAgent');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    {{-- Header --}}
    <div data-test="work-item-header">
        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="xl">{{ $workItem->title }}</flux:heading>
                <flux:text class="mt-1 font-mono text-sm">{{ $workItem->provider_key }}</flux:text>
            </div>
            @if ($workItem->url)
                <a href="{{ $workItem->url }}" target="_blank" rel="noopener noreferrer">
                    <flux:button size="sm" data-test="external-link">{{ __('Open in :provider', ['provider' => ucfirst($workItem->provider)]) }} ↗</flux:button>
                </a>
            @endif
        </div>
        <div class="mt-3 flex flex-wrap gap-3">
            @if ($workItem->type)
                <flux:badge size="sm" variant="pill" data-test="work-item-type">{{ $workItem->type }}</flux:badge>
            @endif
            @if ($workItem->status)
                <flux:badge size="sm" variant="outline" data-test="work-item-status">{{ $workItem->status }}</flux:badge>
            @endif
            @if ($workItem->priority)
                <flux:badge size="sm" variant="pill" data-test="work-item-priority">{{ $workItem->priority }}</flux:badge>
            @endif
            @if ($workItem->classified_type)
                <flux:badge size="sm" variant="pill" color="indigo" data-test="work-item-classified-type">{{ $workItem->classified_type }}</flux:badge>
            @endif
            @if ($workItem->assignee)
                <flux:badge size="sm" variant="pill" data-test="work-item-assignee">{{ $workItem->assignee }}</flux:badge>
            @endif
        </div>
    </div>

    {{-- Description --}}
    @if ($workItem->description)
        <div data-test="work-item-description">
            <flux:heading size="lg">{{ __('Description') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $workItem->description }}</flux:text>
        </div>
    @endif

    {{-- HITL Escalations --}}
    @if ($workItem->hitlEscalations->isNotEmpty())
        <div data-test="work-item-escalations">
            <flux:heading size="lg">{{ __('HITL Escalations') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($workItem->hitlEscalations as $escalation)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="escalation-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $escalation->trigger_type }}</flux:badge>
                            @if ($escalation->isResolved())
                                <flux:badge size="sm" variant="pill" color="green">{{ __('Resolved') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="pill" color="red">{{ __('Unresolved') }}</flux:badge>
                            @endif
                            @if ($escalation->agent_confidence !== null)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Confidence: :pct%', ['pct' => round($escalation->agent_confidence * 100)]) }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-1 text-sm">{{ $escalation->reason }}</flux:text>
                        @if ($escalation->raisedByAgent)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Raised by :agent', ['agent' => $escalation->raisedByAgent->name]) }}</flux:text>
                        @endif

                        @if ($escalation->isResolved())
                            <div class="mt-2 rounded bg-green-50 p-3 dark:bg-green-900/20" data-test="escalation-resolution">
                                <flux:text class="text-sm font-medium">{{ __('Resolution: :resolution', ['resolution' => $escalation->resolution]) }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Resolved by :name on :date', ['name' => $escalation->resolved_by, 'date' => $escalation->resolved_at->format('M j, Y g:i A')]) }}</flux:text>
                            </div>
                        @elseif ($escalation->trigger_type === 'reclassification')
                            <div class="mt-3 flex items-center gap-2" data-test="escalation-actions">
                                <flux:button size="sm" variant="primary" wire:click="confirmReclassification({{ $escalation->id }})" data-test="confirm-reclassification-button">{{ __('Confirm') }}</flux:button>
                                <flux:button size="sm" wire:click="revertReclassification({{ $escalation->id }})" data-test="revert-reclassification-button">{{ __('Revert') }}</flux:button>
                            </div>
                        @elseif ($escalation->trigger_type === 'type_label_suggestion')
                            @if (! empty($escalation->metadata['suggested_labels']))
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($escalation->metadata['suggested_labels'] as $label)
                                        <flux:badge size="sm" variant="outline">{{ $label }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                            <div class="mt-3 flex items-center gap-2" data-test="escalation-actions">
                                <flux:button size="sm" variant="primary" wire:click="approveTypeLabels({{ $escalation->id }})" data-test="approve-labels-button">{{ __('Approve') }}</flux:button>
                                <flux:button size="sm" wire:click="rejectTypeLabels({{ $escalation->id }})" data-test="reject-labels-button">{{ __('Reject') }}</flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Conversation --}}
    <div data-test="work-item-conversation" class="flex flex-1 flex-col">
        <flux:heading size="lg">{{ __('Conversation') }}</flux:heading>

        <div class="mt-2 flex flex-1 flex-col rounded-lg border border-zinc-200 dark:border-zinc-700">
            {{-- Messages --}}
            <div class="flex-1 space-y-4 overflow-y-auto p-4" data-test="message-list">
                @forelse ($this->chatMessages as $message)
                    <div wire:key="msg-{{ $message->id }}" @class([
                        'flex',
                        'justify-end' => $message->role === 'user',
                        'justify-start' => $message->role !== 'user',
                    ])>
                        <div @class([
                            'max-w-[80%] rounded-lg px-4 py-3',
                            'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-100' => $message->role === 'user',
                            'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' => $message->role === 'assistant',
                        ]) data-test="message-bubble">
                            <div class="mb-1 text-xs font-medium opacity-60">
                                {{ $message->role === 'user' ? __('You') : __('Agent') }}
                                <span class="ml-2">{{ $message->created_at->format('M j, g:i A') }}</span>
                            </div>
                            <div class="whitespace-pre-wrap text-sm">{{ $message->content }}</div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-1 items-center justify-center py-8 text-center">
                        <flux:text class="text-zinc-400 dark:text-zinc-500">{{ __('No messages yet.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            {{-- Input --}}
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700" data-test="message-input">
                <form wire:submit="sendMessage" class="flex gap-3">
                    <div class="flex-1">
                        <flux:textarea wire:model="newMessage" :placeholder="__('Type a message...')" rows="2" data-test="message-textarea" />
                    </div>
                    <div class="flex items-end">
                        <flux:button type="submit" variant="primary" data-test="send-button">{{ __('Send') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
