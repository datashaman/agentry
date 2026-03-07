<?php

use App\Models\AgentConversation;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Chats')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $userId = '';

    #[Computed]
    public function users(): array
    {
        return User::query()
            ->whereIn('id', AgentConversation::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function conversations(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AgentConversation::query()
            ->withCount('messages')
            ->with('user');

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        if ($this->userId === 'anonymous') {
            $query->whereNull('user_id');
        } elseif ($this->userId !== '') {
            $query->where('user_id', $this->userId);
        }

        return $query->latest('updated_at')->paginate(20);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Chats') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse all agent conversations for debugging.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search by title...')" size="sm" data-test="search-input" />
        </div>

        <select wire:model.live="userId" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="user-filter">
            <option value="">{{ __('All Users') }}</option>
            <option value="anonymous">{{ __('Anonymous') }}</option>
            @foreach ($this->users as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->conversations->isEmpty())
        <div class="flex flex-1 items-center justify-center" data-test="empty-state">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Chats') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No conversations match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('User') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Messages') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->conversations as $conversation)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="chat-row" wire:key="chat-{{ $conversation->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('chats.show', $conversation) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="chat-link">
                                    {{ $conversation->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $conversation->user?->name ?? __('Anonymous') }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text data-test="message-count">{{ $conversation->messages_count }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $conversation->created_at->format('M j, Y') }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $conversation->updated_at->format('M j, Y') }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->conversations->hasPages())
            <div class="mt-4">
                {{ $this->conversations->links() }}
            </div>
        @endif
    @endif
</div>
