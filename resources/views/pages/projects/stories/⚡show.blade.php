<?php

use App\Models\Project;
use App\Models\Story;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Story')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Story $story;
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$project->organization" :project="$project" />

    <flux:heading size="xl">{{ $story->title }}</flux:heading>
    <flux:text>{{ __('Story detail coming soon.') }}</flux:text>
</div>
