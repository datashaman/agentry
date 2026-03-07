<?php

use App\Models\AgentConversation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chat Detail')] #[Layout('layouts.app')] class extends Component {
    public AgentConversation $conversation;

    public function mount(AgentConversation $conversation): void
    {
        $this->conversation = $conversation->load('user');
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->conversation->messages()->oldest()->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div data-test="chat-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('chats.index') }}" wire:navigate>
                <flux:button size="sm" variant="ghost">&larr; {{ __('Back') }}</flux:button>
            </a>
            <flux:heading size="xl">{{ $conversation->title }}</flux:heading>
        </div>
        <div class="mt-2 flex flex-wrap gap-3">
            <flux:text class="text-sm">
                <span class="font-medium">{{ __('User:') }}</span>
                {{ $conversation->user?->name ?? __('Anonymous') }}
            </flux:text>
            <flux:text class="text-sm">
                <span class="font-medium">{{ __('Created:') }}</span>
                {{ $conversation->created_at->format('M j, Y g:i A') }}
            </flux:text>
            <flux:text class="text-sm">
                <span class="font-medium">{{ __('Updated:') }}</span>
                {{ $conversation->updated_at->format('M j, Y g:i A') }}
            </flux:text>
        </div>
    </div>

    {{-- Messages --}}
    <div class="space-y-4" data-test="message-list">
        @forelse ($this->messages as $message)
            <div wire:key="msg-{{ $message->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="message-item">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($message->role === 'user')
                        <flux:badge size="sm" variant="pill" color="blue" data-test="role-badge">{{ __('User') }}</flux:badge>
                    @elseif ($message->role === 'assistant')
                        <flux:badge size="sm" variant="pill" color="green" data-test="role-badge">{{ __('Assistant') }}</flux:badge>
                    @else
                        <flux:badge size="sm" variant="pill" data-test="role-badge">{{ $message->role }}</flux:badge>
                    @endif

                    @if ($message->agent)
                        <flux:badge size="sm" variant="outline" data-test="agent-badge">{{ class_basename($message->agent) }}</flux:badge>
                    @endif

                    <flux:text class="text-xs text-zinc-400">{{ $message->created_at->format('M j, Y g:i A') }}</flux:text>
                </div>

                <div class="mt-2 whitespace-pre-wrap text-sm" data-test="message-content">{{ $message->content }}</div>

                @if (! empty($message->tool_calls))
                    <details class="mt-3" data-test="tool-calls">
                        <summary class="cursor-pointer text-xs font-medium text-zinc-500">{{ __('Tool Calls') }}</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ json_encode($message->tool_calls, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif

                @if (! empty($message->tool_results))
                    <details class="mt-2" data-test="tool-results">
                        <summary class="cursor-pointer text-xs font-medium text-zinc-500">{{ __('Tool Results') }}</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ json_encode($message->tool_results, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif

                @if (! empty($message->attachments))
                    <details class="mt-2" data-test="attachments">
                        <summary class="cursor-pointer text-xs font-medium text-zinc-500">{{ __('Attachments') }}</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ json_encode($message->attachments, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif

                @if (! empty($message->usage))
                    <details class="mt-2" data-test="usage">
                        <summary class="cursor-pointer text-xs font-medium text-zinc-500">{{ __('Usage') }}</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ json_encode($message->usage, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif

                @if (! empty($message->meta))
                    <details class="mt-2" data-test="meta">
                        <summary class="cursor-pointer text-xs font-medium text-zinc-500">{{ __('Meta') }}</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">{{ json_encode($message->meta, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @endif
            </div>
        @empty
            <div class="flex flex-1 items-center justify-center py-8 text-center" data-test="empty-state">
                <flux:text class="text-zinc-400 dark:text-zinc-500">{{ __('No messages in this conversation.') }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
