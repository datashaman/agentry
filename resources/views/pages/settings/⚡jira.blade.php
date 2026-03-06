<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Jira settings')] class extends Component {
    /**
     * Disconnect the Jira account.
     */
    public function disconnect(): void
    {
        Auth::user()->update([
            'jira_account_id' => null,
            'jira_token' => null,
            'jira_refresh_token' => null,
            'jira_cloud_id' => null,
        ]);

        session()->flash('status', 'jira-disconnected');

        $this->redirect(route('jira.edit'), navigate: true);
    }

    #[Computed]
    public function hasJira(): bool
    {
        return Auth::user()->hasJira();
    }

    #[Computed]
    public function jiraCloudId(): ?string
    {
        return Auth::user()->jira_cloud_id;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Jira')" :subheading="__('Connect your Jira account to access work items')">
        <div class="my-6 space-y-6">
            @if (session('status') === 'jira-connected')
                <flux:text class="font-medium !dark:text-green-400 !text-green-600">
                    {{ __('Jira account connected successfully.') }}
                </flux:text>
            @endif

            @if (session('status') === 'jira-disconnected')
                <flux:text class="font-medium !dark:text-green-400 !text-green-600">
                    {{ __('Jira account disconnected.') }}
                </flux:text>
            @endif

            @if (session('status') === 'jira-error')
                <flux:text class="font-medium !dark:text-red-400 !text-red-600">
                    {{ __('Failed to connect Jira. Please try again.') }}
                </flux:text>
            @endif

            @if ($this->hasJira)
                <div class="flex items-center gap-4">
                    <div>
                        <flux:text class="font-medium">{{ __('Connected') }}</flux:text>
                        <flux:text class="text-lg font-semibold">{{ __('Cloud ID:') }} {{ $this->jiraCloudId }}</flux:text>
                    </div>
                </div>

                <flux:button variant="danger" wire:click="disconnect" wire:confirm="{{ __('Are you sure you want to disconnect your Jira account?') }}">
                    {{ __('Disconnect Jira') }}
                </flux:button>
            @else
                <flux:text>{{ __('Connect your Jira account to allow agents to access your Jira work items.') }}</flux:text>

                <flux:button variant="primary" :href="route('jira.redirect')">
                    {{ __('Connect Jira') }}
                </flux:button>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
