<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('GitHub settings')] class extends Component {
    /**
     * Disconnect the GitHub account.
     */
    public function disconnect(): void
    {
        Auth::user()->update([
            'github_id' => null,
            'github_token' => null,
            'github_nickname' => null,
        ]);

        session()->flash('status', 'github-disconnected');

        $this->redirect(route('github.edit'), navigate: true);
    }

    #[Computed]
    public function hasGitHub(): bool
    {
        return Auth::user()->hasGitHub();
    }

    #[Computed]
    public function gitHubNickname(): ?string
    {
        return Auth::user()->github_nickname;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('GitHub')" :subheading="__('Connect your GitHub account to access repositories')">
        <div class="my-6 space-y-6">
            @if (session('status') === 'github-connected')
                <flux:text class="font-medium !dark:text-green-400 !text-green-600">
                    {{ __('GitHub account connected successfully.') }}
                </flux:text>
            @endif

            @if (session('status') === 'github-disconnected')
                <flux:text class="font-medium !dark:text-green-400 !text-green-600">
                    {{ __('GitHub account disconnected.') }}
                </flux:text>
            @endif

            @if ($this->hasGitHub)
                <div class="flex items-center gap-4">
                    <div>
                        <flux:text class="font-medium">{{ __('Connected as') }}</flux:text>
                        <flux:text class="text-lg font-semibold">{{ $this->gitHubNickname }}</flux:text>
                    </div>
                </div>

                <flux:button variant="danger" wire:click="disconnect" wire:confirm="{{ __('Are you sure you want to disconnect your GitHub account?') }}">
                    {{ __('Disconnect GitHub') }}
                </flux:button>
            @else
                <flux:text>{{ __('Connect your GitHub account to allow agents to access your repositories.') }}</flux:text>

                <flux:button variant="primary" :href="route('github.redirect')">
                    {{ __('Connect GitHub') }}
                </flux:button>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
