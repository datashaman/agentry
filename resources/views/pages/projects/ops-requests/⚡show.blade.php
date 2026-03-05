<?php

use App\Models\OpsRequest;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ops Request')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public OpsRequest $opsRequest;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ $opsRequest->title }}</flux:heading>
        <flux:text class="mt-1">{{ str_replace('_', ' ', ucfirst($opsRequest->status)) }}</flux:text>
    </div>
</div>
