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
    <div class="flex flex-1 flex-col rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="flex-1 space-y-4 overflow-y-auto p-4" data-test="message-list">
            @forelse ($this->messages as $message)
                @if ($message->role === 'system')
                    {{-- System messages: collapsible with title badge --}}
                    <div wire:key="msg-{{ $message->id }}" class="flex justify-center" data-test="message-item">
                        <details class="w-full max-w-[90%]" data-test="system-message">
                            <summary class="flex cursor-pointer items-center justify-center gap-2">
                                <flux:badge size="sm" variant="pill" color="amber" data-test="role-badge">{{ __('System') }}</flux:badge>
                                @if ($message->agent)
                                    <flux:badge size="sm" variant="outline" data-test="agent-badge">{{ class_basename($message->agent) }}</flux:badge>
                                @endif
                                <flux:text class="text-xs text-zinc-400">{{ $message->created_at->format('M j, Y g:i A') }}</flux:text>
                            </summary>
                            <div class="mt-2 rounded-lg bg-amber-50 px-4 py-3 dark:bg-amber-900/20">
                                <div class="whitespace-pre-wrap text-sm text-amber-900 dark:text-amber-100" data-test="message-content">{{ $message->content }}</div>
                            </div>
                        </details>
                    </div>
                @else
                    {{-- User/Assistant messages: chat bubbles --}}
                    <div wire:key="msg-{{ $message->id }}" @class([
                        'flex',
                        'justify-end' => $message->role === 'user',
                        'justify-start' => $message->role !== 'user',
                    ]) data-test="message-item">
                        <div @class([
                            'max-w-[80%] rounded-lg px-4 py-3',
                            'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-100' => $message->role === 'user',
                            'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' => $message->role === 'assistant',
                        ]) data-test="message-bubble">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
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

                                <span class="text-xs opacity-60">{{ $message->created_at->format('M j, g:i A') }}</span>
                            </div>

                            <div class="whitespace-pre-wrap text-sm" data-test="message-content">{{ $message->content }}</div>

                            @if (! empty($message->tool_calls))
                                <details class="mt-3" data-test="tool-calls">
                                    <summary class="cursor-pointer text-xs font-medium opacity-60">{{ __('Tool Calls') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20">{{ json_encode($message->tool_calls, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif

                            @if (! empty($message->tool_results))
                                <details class="mt-2" data-test="tool-results">
                                    <summary class="cursor-pointer text-xs font-medium opacity-60">{{ __('Tool Results') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20">{{ json_encode($message->tool_results, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif

                            @if (! empty($message->attachments))
                                <details class="mt-2" data-test="attachments">
                                    <summary class="cursor-pointer text-xs font-medium opacity-60">{{ __('Attachments') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20">{{ json_encode($message->attachments, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif

                            @if (! empty($message->usage))
                                <details class="mt-2" data-test="usage">
                                    <summary class="cursor-pointer text-xs font-medium opacity-60">{{ __('Usage') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20">{{ json_encode($message->usage, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif

                            @if (! empty($message->meta))
                                <details class="mt-2" data-test="meta">
                                    <summary class="cursor-pointer text-xs font-medium opacity-60">{{ __('Meta') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20">{{ json_encode($message->meta, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex flex-1 items-center justify-center py-8 text-center" data-test="empty-state">
                    <flux:text class="text-zinc-400 dark:text-zinc-500">{{ __('No messages in this conversation.') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>
