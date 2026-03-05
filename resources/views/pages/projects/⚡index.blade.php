<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Projects')] #[Layout('layouts.app')] class extends Component {
    //
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Projects') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse projects in your organization.') }}</flux:text>
    </div>
</div>
