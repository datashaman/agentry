<?php

use App\Models\Project;
use App\Models\Repo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Repository Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Repo $repo;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ $repo->name }}</flux:heading>
        <flux:text class="mt-1">{{ __('Repository detail page - coming soon.') }}</flux:text>
    </div>
</div>
