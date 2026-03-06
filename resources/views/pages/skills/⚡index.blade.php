<?php

use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Skills')] #[Layout('layouts.app')] class extends Component {
    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function skills(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return Skill::query()
            ->where('organization_id', $this->organization->id)
            ->withCount('agentRoles')
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Skills') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Domain-specific capability packages that extend agent instructions.') }}</flux:text>
        </div>
        <a href="{{ route('skills.create') }}" wire:navigate data-test="create-skill-button">
            <flux:button variant="primary">{{ __('New Skill') }}</flux:button>
        </a>
    </div>

    @if ($this->skills->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Skills') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No skills have been created yet.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Slug') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Agent Roles') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->skills as $skill)
                        <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="skill-row" wire:key="skill-{{ $skill->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('skills.show', $skill) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="skill-link">
                                    {{ $skill->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ $skill->slug }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="truncate">{{ Str::limit($skill->description, 60) }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $skill->agent_roles_count }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to manage skills.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
