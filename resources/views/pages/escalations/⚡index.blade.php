<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Escalations')] #[Layout('layouts.app')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Escalations') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Manage HITL escalations across your organization.') }}</flux:text>
    </div>
</div>
