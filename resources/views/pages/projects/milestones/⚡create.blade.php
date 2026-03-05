<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Milestone')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('New Milestone') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Create a new milestone for :project.', ['project' => $project->name]) }}</flux:text>
    </div>
</div>
