<?php

use App\Models\Project;
use App\Models\WorkItem;
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
        $this->workItem->loadMissing('conversation.messages');
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function chatMessages(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->workItem->conversation) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return $this->workItem->conversation->messages()
            ->where('role', '!=', 'system')
            ->oldest()
            ->get();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'newMessage' => 'required|string|min:1',
        ]);

        if (! $this->workItem->conversation) {
            $this->workItem->conversation()->create();
            $this->workItem->load('conversation');
        }

        $this->workItem->conversation->messages()->create([
            'role' => 'user',
            'content' => $this->newMessage,
        ]);

        $this->newMessage = '';
        unset($this->chatMessages);
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
                @php
                    $classifiedColors = ['bug' => 'red', 'story' => 'blue', 'ops_request' => 'amber'];
                @endphp
                <flux:badge size="sm" variant="pill" :color="$classifiedColors[$workItem->classified_type] ?? 'zinc'" data-test="work-item-classified-type">{{ str_replace('_', ' ', $workItem->classified_type) }}</flux:badge>
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
