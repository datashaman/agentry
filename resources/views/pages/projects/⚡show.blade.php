<?php

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] #[Layout('layouts.app')] class extends Component {
    public Project $project;
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ $project->name }}</flux:heading>
        <flux:text class="mt-1">{{ $project->slug }}</flux:text>
    </div>
</div>
