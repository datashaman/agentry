<?php

use App\Models\Bug;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bug Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Bug $bug;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ $bug->title }}</flux:heading>
        <flux:text class="mt-1">{{ __('Bug detail page — coming soon.') }}</flux:text>
    </div>
</div>
