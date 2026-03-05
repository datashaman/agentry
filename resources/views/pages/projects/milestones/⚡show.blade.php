<?php

use App\Models\Milestone;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Milestone')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Milestone $milestone;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ $milestone->title }}</flux:heading>
        <div class="mt-2 flex items-center gap-3">
            <flux:badge size="sm" variant="pill">{{ $milestone->status }}</flux:badge>
            @if ($milestone->due_date)
                <flux:text class="text-sm">{{ __('Due :date', ['date' => $milestone->due_date->format('M j, Y')]) }}</flux:text>
            @endif
        </div>
        @if ($milestone->description)
            <flux:text class="mt-2">{{ $milestone->description }}</flux:text>
        @endif
    </div>
</div>
